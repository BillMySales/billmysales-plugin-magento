#!/usr/bin/env bash
#
# BillMySales for Magento: end-to-end tests (make e2e).
#
# Clones the Mage-OS Docker stack into var/e2e/stack, starts it, installs
# the module the way a merchant using Composer would (a "path" repository
# pointing at plugin/, required as a normal dependency: see install_plugin,
# below), and runs each test case against a local webhook receiver
# (tests/e2e/receiver.php, standing in for BillMySales): the case does
# something in Magento (through the checkout's own REST endpoints, the
# settings form, or the module's own scheduled job), the module sends the
# order, and tests/e2e/check.php checks what arrived (signature, headers,
# payload, the order's JSON Schema). Installing this way (rather than a
# bind mount or the built zip copied into app/code by hand) means the last
# case can run the real `bin/magento module:uninstall --remove-data`
# instead of a workaround: the module a zip install places directly in
# app/code isn't a Composer package, and that command only proceeds for
# one that is (tests/install/run.sh separately checks the zip itself still
# installs and uninstalls in that raw, non-Composer way, since that's the
# module's actual current distribution channel). At the end the stack
# (containers, volumes, clone) and the receiver are removed; the results
# stay in var/e2e until the next run or make clean: webhooks/<case>-<time>.
# body (raw body) and .json (headers, decoded payload, status answered),
# real deliveries to look at or to replay against BillMySales; checkout.
# json, stack.log. E2E_KEEP=1 also keeps the stack running (then make
# e2e-clean).
#
# Adding to cart, the address, the checkout fields and placing the order go
# through the same REST endpoints the checkout's own JavaScript calls
# (/rest/default/V1/guest-carts/...), exactly as a shopper's browser would:
# the checkout field's value is combined into its extension attribute here
# the same way view/frontend/web/js/collect-checkout-fields.js does before
# a real submission. tests/e2e/fixtures.php, copied into the "magento"
# container, bootstraps Magento itself (like bin/magento does) for the one
# thing its own CLI has no command for: a simple product, and an order's
# status change through its real repository save (so the module's own
# observer fires exactly as it does for a real order). The scheduled job
# is run once by calling BillMySales\Magento\Cron\ProcessDueDeliveries
# directly (fixtures.php run-queue): Magento has no single command to run
# one cron job now regardless of its own schedule (unlike WooCommerce's
# Action Scheduler or Dolibarr's own scripts/cron/cron_run_jobs.php).
# Settings saved through the admin form, and "Send to BillMySales", go
# through plain HTTP with curl, cookies and the admin's own secret-key
# links, exactly as a browser would.
#
# Needs only Docker on the host. The stack's development ports and the
# receiver's (8099) must be free: stop the Magento development stack.
#
# Environment: TOOLS_IMAGE (set by the Makefile), STACK_REPO, STACK_REF
# (default master), E2E_KEEP, E2E_STACK_ENV (extra "NAME=value" lines, one
# per line, appended to the stack's .env; this stack validates only one
# row, so there's no other MAGEOS_VERSION/PHP_VERSION combination to
# select, but this still lets other variables be overridden the same way
# every plugin's e2e does), E2E_RECEIVER_PORT (default 8099, for when
# another end-to-end run holds it).

set -euo pipefail

PLATFORM=magento
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
E2E="${ROOT}/var/e2e"
STACK="${E2E}/stack"
PROJECT="${PLATFORM}-e2e"
STACK_REPO="${STACK_REPO:-https://github.com/BillMySales/billmysales-docker-${PLATFORM}.git}"
STACK_REF="${STACK_REF:-master}"
TOOLS_IMAGE="${TOOLS_IMAGE:?Run it with make e2e}"
RECEIVER="${PROJECT}-receiver"
RECEIVER_PORT="${E2E_RECEIVER_PORT:-8099}"
RECEIVER_URL="http://host.docker.internal:${RECEIVER_PORT}/"
SECRET='e2e "secret"\x'
PRODUCT_SKU=billmysales-e2e
VERSION="$(sed -n 's/^ *"version": *"\(.*\)",\{0,1\}$/\1/p' "${ROOT}/plugin/composer.json")"
FAILED=0
FROM=0

# --- Helpers -----------------------------------------------------------------

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
fail() { printf '    ✘ %s\n' "$*"; FAILED=1; }
pass() { printf '    ✔ %s\n' "$*"; }
expect() { if [ "$1" = "$2" ]; then pass "$3"; else fail "$3 (got \"$1\", expected \"$2\")"; fi; }

compose() { (cd "${STACK}" && docker compose "$@"); }
# tests/e2e/fixtures.php inside the "magento" container: a simple product,
# and an order's status change through its real repository save.
fx() { compose exec -T -u www-data magento php e2e-fixtures.php "$@" 2>> "${E2E}/stack.log" | tr -d '\r'; }
# Runs BillMySales_Magento's own scheduled job once (fixtures.php run-queue).
run_queue() { fx run-queue > /dev/null; }
received() { find "${E2E}/webhooks" -name '*.json' | wc -l | tr -d ' '; }
respond() { echo "$1" > "${E2E}/respond"; }
port_in_use() { (exec 3<> "/dev/tcp/127.0.0.1/$1") 2> /dev/null; }
env_value() { { printf '%s\n' "${E2E_STACK_ENV:-}"; cat "${STACK}/.env.dev.example"; } | sed -n "s/^$1=//p" | head -1; }
# JSON-encodes a string for embedding as a JSON string value.
json_string() { printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'; }

# Retries a command a few times: works around a startup race, seen live,
# where the app container is still generating classes into generated/code
# while module:enable/setup:upgrade clears it (PHP's rmdir() then fails
# with "Directory not empty", since it only removes empty directories).
retry() { local i; for i in 1 2 3; do "$@" && return 0; sleep 5; done; return 1; }

# Starts a test case: what the receiver got before it isn't checked.
case_start() { printf '\n[%s] %s\n' "$1" "$2"; printf '%02d' "$1" > "${E2E}/case"; FROM="$(received)"; }

# Checks the requests received since case_start (tests/e2e/check.php).
check() {
    docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -v "${ROOT}:/app" -w /app "${TOOLS_IMAGE}" \
        php tests/e2e/check.php var/e2e/webhooks "${FROM}" "$1" || FAILED=1
}

# Expectations of a delivered order (check.php's JSON). Reads the order's
# own grand_total (fixtures.php get-order-field) instead of assuming a
# shipping rate.
order_expectations() { # <order id> <status> [extra JSON members]
    local total
    total="$(fx get-order-field "$1" grand_total)"
    printf '{"count": 1, "secret": "%s", "platform": "%s", "plugin_version": "%s", "source": "%s/", "event": "%s", "schema": "tests/e2e/order-schema.json", "order_id": %s, "status": "%s", "total": "%s"%s}' \
        "${SECRET_JSON}" "${PLATFORM}" "${VERSION}" "${MAGE_URL}" "${EVENT:-order.status_changed}" "$1" "$2" "${total}" "${3:+, $3}"
}

# Sets an order's status (and state, defaulting to the same value) through
# its real repository save, so the module's own observer fires.
set_order_status() { fx set-order-status "$1" "$2" "${3:-$2}" > /dev/null; }

# Makes a queued delivery due right now, instead of waiting for its retry
# delay to elapse.
force_retry_now() { # <order id>
    compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento \
        -e "UPDATE billmysales_delivery_queue SET next_attempt_at = NOW() WHERE order_id = $1;" >> "${E2E}/stack.log" 2>&1
}

# Places an order through the same REST endpoints the checkout's own
# JavaScript calls: a guest cart, the product, the address (with the
# checkout fields, combined into their extension attribute exactly as
# collect-checkout-fields.js does), then payment. Prints the order id
# (empty when the address step was refused; its response goes to
# var/e2e/checkout.json).
checkout() { # <checkout fields JSON, e.g. '{"rut": "11.111.111-1"}'>
    local cart address http_code
    cart="$(curl -fsS -X POST "${MAGE_URL}/rest/default/V1/guest-carts" -H 'Content-Type: application/json' -d '{}' | tr -d '"')"
    curl -fsS -o /dev/null -X POST "${MAGE_URL}/rest/default/V1/guest-carts/${cart}/items" -H 'Content-Type: application/json' \
        -d "{\"cartItem\": {\"sku\": \"${PRODUCT_SKU}\", \"qty\": 1, \"quote_id\": \"${cart}\"}}"
    address='"region": "Región Metropolitana de Santiago", "region_id": 681, "country_id": "CL", "street": ["Av. Siempre Viva 123"], "telephone": "+56911111111", "postcode": "8320000", "city": "Santiago", "firstname": "Ana", "lastname": "Pérez", "email": "ana@example.com"'
    http_code="$(curl -sS -o "${E2E}/checkout.json" -w '%{http_code}' -X POST "${MAGE_URL}/rest/default/V1/guest-carts/${cart}/shipping-information" -H 'Content-Type: application/json' \
        -d "{\"addressInformation\": {\"shipping_address\": {${address}}, \"billing_address\": {${address}}, \"shipping_method_code\": \"flatrate\", \"shipping_carrier_code\": \"flatrate\", \"extension_attributes\": {\"billmysales_checkout_fields\": \"$(json_string "$1")\"}}}")"
    [ "${http_code}" -lt 300 ] || return 0
    curl -fsS -X POST "${MAGE_URL}/rest/default/V1/guest-carts/${cart}/payment-information" -H 'Content-Type: application/json' \
        -d "{\"cartId\": \"${cart}\", \"email\": \"ana@example.com\", \"paymentMethod\": {\"method\": \"checkmo\"}, \"billingAddress\": {${address}}}" | tr -d '"'
}

# Copies tests/e2e/fixtures.php into the "magento" container.
copy_fixtures() { compose cp "${ROOT}/tests/e2e/fixtures.php" magento:/var/www/magento/e2e-fixtures.php; }

# Sets a configuration value through the admin's own CLI (its own
# backend_model, e.g. the secret's encryption, runs the same as it would
# through the settings form).
config_set() { compose exec -T -u www-data magento bin/magento config:set --scope=default "$1" "$2" > /dev/null 2>> "${E2E}/stack.log"; }

# Finds the full URL of a link to a given admin path in a page's own
# rendering (found empirically: Magento's admin security key is bound to
# the target controller, not to the page or the session, so a key is only
# valid for the exact link it was embedded in; the reliable way to reach a
# page is to follow a link to it from a page that actually renders one,
# exactly as a browser would, never to reuse a key found elsewhere).
admin_link() { # <page HTML> <path fragment> [attribute, default href]
    printf '%s' "$1" | grep -o "${3:-href}=\"[^\"]*$2[^\"]*\"" | head -1 | sed "s/^${3:-href}=\"//;s/\"\$//"
}

# GET/POST as the admin (admin_login's cookies).
admin_get() { curl -fsS -c "${E2E}/admin-cookies" -b "${E2E}/admin-cookies" -L "$@"; }
# No "-X POST": curl infers it from --data-urlencode, and lets a redirect
# (Magento's save controllers all answer with one) switch to GET, as a
# browser would; forcing "-X POST" makes curl re-POST every redirect hop,
# looping against the edit page's own redirects (found live: "curl: (47)
# Maximum (50) redirects followed").
admin_post() { curl -fsS -c "${E2E}/admin-cookies" -b "${E2E}/admin-cookies" -L "$@"; }

# Logs in to the back office as the development admin.
admin_login() {
    rm -f "${E2E}/admin-cookies"
    local login form_key
    login="$(admin_get "${MAGE_URL}/${MAGE_ADMIN_PATH}/admin/")"
    form_key="$(printf '%s' "${login}" | grep -o "var FORM_KEY = '[^']*'" | sed "s/.*'\\(.*\\)'/\\1/")"
    admin_get "${MAGE_URL}/${MAGE_ADMIN_PATH}/admin/" \
        --data-urlencode "login[username]=$(env_value MAGE_ADMIN_USER)" --data-urlencode "login[password]=$(env_value MAGE_ADMIN_PASSWORD)" \
        --data-urlencode "form_key=${form_key}" > /dev/null
}

# The order view page, reached the same way a browser would: from the
# dashboard's own orders link, to the grid, to the order. The grid's
# initial data embeds every row's own view link as JSON, key included; the
# key is the same across every row in one response (bound to the
# controller, not the order id), so any row's link can be reused for the
# order we actually want by swapping its order_id.
order_view_page() { # <order id>
    local dashboard orders_url grid view_url
    dashboard="$(admin_get "${MAGE_URL}/${MAGE_ADMIN_PATH}/admin/dashboard/index/")"
    orders_url="$(admin_link "${dashboard}" 'sales/order/index')"
    [ -n "${orders_url}" ] || { fail "no orders link on the dashboard"; return 1; }
    grid="$(admin_get "${orders_url}")"
    view_url="$(printf '%s' "${grid}" | grep -o '"view":{"href":"[^"]*"' | head -1 | sed 's#.*"href":"##;s#"$##;s#\\/#/#g')"
    [ -n "${view_url}" ] || { fail "no order view link in the orders grid"; return 1; }
    admin_get "$(printf '%s' "${view_url}" | sed -E "s#(order_id/)[0-9]+#\\1$1#")"
}

# "Send to BillMySales" (the order view page's own link): reads the order
# view page for its own resend link (Magento already embeds a valid
# security key in it) and follows it.
resend_order() { # <order id>
    local page url
    page="$(order_view_page "$1")" || return
    url="$(admin_link "${page}" 'billmysales/order/resend')"
    [ -n "${url}" ] || { fail "no \"Send to BillMySales\" link on the order page"; return; }
    admin_get "${url}" > /dev/null
}

# Saves the settings and the checkout fields through the real admin form
# (the config page's own save action, with its own security key and
# form_key): the same fields the row editor's JS keeps in sync
# (view/adminhtml/web/js/checkout-fields-editor.js), submitted directly.
# Reached the same way a browser would: dashboard -> settings link ->
# billmysales section link (never a key reused from another controller).
save_settings_via_form() { # <token> <checkout fields definitions JSON>
    local dashboard config_url page section_url form_key save_url
    dashboard="$(admin_get "${MAGE_URL}/${MAGE_ADMIN_PATH}/admin/dashboard/index/")"
    config_url="$(admin_link "${dashboard}" 'system_config/index')"
    [ -n "${config_url}" ] || { fail "no settings link on the dashboard"; return; }
    page="$(admin_get "${config_url}")"
    section_url="$(admin_link "${page}" 'system_config/edit/section/billmysales')"
    [ -n "${section_url}" ] || { fail "no billmysales settings link"; return; }
    page="$(admin_get "${section_url}")"
    form_key="$(printf '%s' "${page}" | grep -o "var FORM_KEY = '[^']*'" | sed "s/.*'\\(.*\\)'/\\1/")"
    save_url="$(admin_link "${page}" 'system_config/save' action)"
    admin_post "${save_url}" \
        --data-urlencode "form_key=${form_key}" \
        --data-urlencode "groups[general][fields][active][value]=1" \
        --data-urlencode "groups[general][fields][webhook][value]=${RECEIVER_URL}" \
        --data-urlencode "groups[general][fields][token][value]=$1" \
        --data-urlencode "groups[general][fields][notify_statuses][value][]=processing" \
        --data-urlencode "groups[general][fields][notify_statuses][value][]=complete" \
        --data-urlencode "groups[checkout_fields][fields][definitions][value]=$2" \
        > /dev/null
}

# "compose up -d --wait" can report failure although every long-running
# service settles healthy moments later (a one-shot "setup" container
# exiting, even successfully): start it, then poll the services that
# matter ourselves instead of trusting its exit code.
up_and_wait() {
    compose up -d >> "${E2E}/stack.log" 2>&1
    local i=0
    for service in db opensearch magento caddy; do
        i=0
        while [ "$(compose ps "${service}" --format '{{.Health}}')" != healthy ]; do
            i=$((i + 1))
            [ "${i}" -ge 60 ] && die "${service} never became healthy (var/e2e/stack.log)"
            sleep 2
        done
    done
    i=0
    while [ "$(compose ps -a setup --format '{{.State}}')" != exited ]; do
        i=$((i + 1))
        [ "${i}" -ge 60 ] && die "setup never finished (var/e2e/stack.log)"
        sleep 2
    done
}

# Writes the stack's .env: the development template, E2E_STACK_ENV's
# overrides if any, and this project name.
stack_env() {
    {
        cat "${STACK}/.env.dev.example"
        if [ -n "${E2E_STACK_ENV:-}" ]; then
            printf '%s\n' "${E2E_STACK_ENV}"
        fi
        echo "COMPOSE_PROJECT_NAME=${PROJECT}"
    } > "${STACK}/.env"
}

# Installs the module the way a merchant using Composer would: copies
# plugin/ in (read-only source; never written to), adds it as a "path"
# repository (Composer symlinks to a path repository by default, so this
# also doubles as a live mount for iterating on the plugin's own code) and
# requires it, then enables it. Never a bind mount into app/code (this
# stack's own dev override) or the built zip copied there by hand
# (tests/install/run.sh checks that, separately): neither is a Composer
# package, and Magento's own module:uninstall only works for one that is.
install_plugin() {
    # Under /var/www/magento, not /tmp: static content deploy resolves
    # every view file's real (symlink-following) path and refuses one
    # outside the project root (found live: "Path ".../order/status.phtml"
    # cannot be used with directory "/var/www/magento/"" while the source
    # was mounted at /tmp).
    compose cp "${ROOT}/plugin" magento:/var/www/magento/.e2e-plugin-src
    {
        compose exec -T -u www-data magento php vendor/composer/composer/bin/composer config repositories.billmysales path .e2e-plugin-src
        # This project was built with `composer create-project --no-dev`:
        # requiring a package without --update-no-dev here reinstalls its
        # entire (large) require-dev set too (found live: 60+ extra
        # packages, rector and codeception among them); "require" has no
        # plain --no-dev (that one's for install/update, "the --no-dev
        # option does not exist" here).
        compose exec -T -u www-data magento php vendor/composer/composer/bin/composer require --update-no-dev --no-interaction 'billmysales/billmysales-plugin-magento:@dev'
    } >> "${E2E}/stack.log" 2>&1
    # Enabling it (and, in production mode, its own static content deploy)
    # is the stack's own job (scripts/setup.sh, MAGE_ENABLE_MODULES): let
    # it, exactly as a real merchant relying on this stack would, instead
    # of re-deriving those same steps here.
    echo "MAGE_ENABLE_MODULES=BillMySales_Magento" >> "${STACK}/.env"
    local i
    for i in 1 2 3; do
        up_and_wait
        [ "$(compose ps -a setup --format '{{.ExitCode}}')" = 0 ] && return 0
        sleep 5
    done
    die "setup never enabled the module (var/e2e/stack.log)"
}

# shellcheck disable=SC2329 # called by the EXIT trap
cleanup() {
    local status=$?
    if [ "${E2E_KEEP:-0}" = 1 ]; then
        say "Stack kept running (E2E_KEEP=1), results in var/e2e; remove with: make e2e-clean"
        return
    fi
    say "Removing the stack and the receiver; results kept in var/e2e (webhooks/, checkout.json, stack.log)"
    [ -f "${STACK}/compose.yaml" ] && compose down -v --remove-orphans > /dev/null 2>&1 || true
    docker rm -f "${RECEIVER}" > /dev/null 2>&1 || true
    rm -rf "${STACK}" "${E2E}/case" "${E2E}/respond" "${E2E}/admin-cookies" "${E2E}/zip-extract"
    exit "${status}"
}

# --- Preparation ---------------------------------------------------------------

if docker ps -aq --filter "name=^${RECEIVER}$" | grep . > /dev/null \
    || docker ps -aq --filter "label=com.docker.compose.project=${PROJECT}" | grep . > /dev/null; then
    die "the stack of a previous run is still there: make e2e-clean"
fi
port_in_use "${RECEIVER_PORT}" && die "port ${RECEIVER_PORT} (webhook receiver) is in use"

rm -rf "${E2E}"
mkdir -p "${E2E}/webhooks"
trap cleanup EXIT

say "Cloning ${STACK_REPO} (${STACK_REF})"
git clone -q --depth 1 --branch "${STACK_REF}" "${STACK_REPO}" "${STACK}"
MAGE_URL="$(env_value MAGE_URL)"
MAGE_ADMIN_PATH="$(env_value MAGE_ADMIN_PATH)"
SECRET_JSON="$(json_string "${SECRET}")"
for port in "$(env_value HTTP_PORT)" "$(env_value HTTPS_PORT)" "$(env_value MAILPIT_PORT)"; do
    port_in_use "${port}" && die "port ${port} is in use: stop the ${PLATFORM} development stack (docker compose down)"
done

say "Starting the webhook receiver (port ${RECEIVER_PORT})"
docker run -d --name "${RECEIVER}" -u "$(id -u):$(id -g)" -p "${RECEIVER_PORT}:${RECEIVER_PORT}" \
    -v "${ROOT}/tests/e2e/receiver.php:/receiver/index.php:ro" -v "${E2E}:/e2e" \
    "${TOOLS_IMAGE}" php -S "0.0.0.0:${RECEIVER_PORT}" -t /receiver > /dev/null

say "Starting the stack (log: var/e2e/stack.log)"
stack_env
up_and_wait
install_plugin
copy_fixtures
fx create-product "${PRODUCT_SKU}" 9990 > /dev/null
compose exec -T -u www-data magento bin/magento indexer:reindex >> "${E2E}/stack.log" 2>&1

config_set billmysales/general/active 1
config_set billmysales/general/webhook "${RECEIVER_URL}"
config_set billmysales/general/token "${SECRET}"
config_set billmysales/general/notify_statuses "processing,complete"
config_set billmysales/checkout_fields/definitions '[{"key":"rut","label":"RUT","values":[],"required":true}]'
FIELDS='{"rut": "11.111.111-1"}'
FIELDS_META='{"rut": "11.111.111-1"}'

# --- Cases -----------------------------------------------------------------------

case_start 1 "Order through the checkout, with the checkout field, status changed to processing"
ORDER="$(checkout "${FIELDS}")"
[ -n "${ORDER}" ] || fail "checkout refused: $(cat "${E2E}/checkout.json")"
set_order_status "${ORDER}" processing
run_queue
check "$(order_expectations "${ORDER}" processing "\"checkout_fields\": ${FIELDS_META}")"

case_start 2 "Checkout without the required field"
REFUSED="$(checkout '{}')"
expect "${REFUSED}" "" "checkout refused"
if grep -q '"RUT"' "${E2E}/checkout.json"; then pass "the error names the field"; else fail "the error doesn't name RUT: $(cat "${E2E}/checkout.json")"; fi
run_queue
check '{"count": 0}'

case_start 3 "Status changed to a second selected status (complete)"
set_order_status "${ORDER}" complete
run_queue
check "$(order_expectations "${ORDER}" complete "\"checkout_fields\": ${FIELDS_META}")"

case_start 4 "Status changed to one not selected (canceled)"
set_order_status "${ORDER}" canceled canceled
run_queue
check '{"count": 0}'

case_start 5 "Deliveries deactivated in the settings"
config_set billmysales/general/active 0
set_order_status "${ORDER}" processing
run_queue
check '{"count": 0}'
config_set billmysales/general/active 1

case_start 6 "BillMySales answers 503: retried with the same delivery"
respond 503
ORDER6="$(checkout "${FIELDS}")"
set_order_status "${ORDER6}" processing
run_queue
PENDING="$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT COUNT(*) FROM billmysales_delivery_queue WHERE order_id = ${ORDER6};" 2>> "${E2E}/stack.log" | tr -d '\r')"
if [ "${PENDING}" = 1 ]; then pass "a retry is scheduled"; else fail "no retry scheduled"; fi
force_retry_now "${ORDER6}"
respond 200
run_queue
check "$(order_expectations "${ORDER6}" processing "\"checkout_fields\": ${FIELDS_META}, \"same_delivery\": true" | sed 's/"count": 1/"count": 2/')"

case_start 7 "BillMySales answers 401: not retried"
respond 401
ORDER7="$(checkout "${FIELDS}")"
set_order_status "${ORDER7}" processing
run_queue
check "$(order_expectations "${ORDER7}" processing "\"checkout_fields\": ${FIELDS_META}")"
PENDING7="$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT COUNT(*) FROM billmysales_delivery_queue WHERE order_id = ${ORDER7};" 2>> "${E2E}/stack.log" | tr -d '\r')"
expect "${PENDING7}" 0 "no retry scheduled"
respond 200

case_start 8 "Sent again from the order view page (\"Send to BillMySales\")"
admin_login
resend_order "${ORDER7}"
run_queue
EVENT=order.resent check "$(EVENT=order.resent order_expectations "${ORDER7}" processing "\"checkout_fields\": ${FIELDS_META}")"

case_start 9 "Settings and checkout fields saved through the admin form"
save_settings_via_form "e2e admin secret" '[{"label":"RUT"}]'
# config:show masks a sensitive path with "******"; the raw stored value
# (checked, like the other database checks below) must be the
# backend_model's own ciphertext, not the plain secret.
TOKEN_STORED="$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT value FROM core_config_data WHERE path='billmysales/general/token';" 2>> "${E2E}/stack.log" | tr -d '\r')"
if printf '%s' "${TOKEN_STORED}" | grep -q ':' && ! printf '%s' "${TOKEN_STORED}" | grep -q 'e2e admin secret'; then
    pass "the token was saved encrypted"
else
    fail "the token wasn't encrypted: ${TOKEN_STORED}"
fi
expect "$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT value FROM core_config_data WHERE path='billmysales/checkout_fields/definitions';" 2>> "${E2E}/stack.log" | tr -d '\r')" \
    '[{"key":"rut","label":"RUT","values":[],"required":false}]' "checkout field's key derived from its label"
config_set billmysales/general/token "${SECRET}" # restored for the remaining cases

case_start 10 "Uninstall"
# The real command: the module was required through Composer (a "path"
# repository, but a real require all the same), so, unlike a bind mount or
# a zip copied into app/code by hand, module:uninstall recognizes it as
# one it installed and runs its own Setup\Uninstall, drops its registry
# rows and removes its code (composer remove, run in-process: Magento
# vendors composer/composer itself, so this needs no composer binary on
# PATH). "-n": no confirmation prompts (their default answer is "no", but
# Symfony's question helper skips asking and takes that default without
# --no-interaction actually blocking the command, since module:uninstall
# only fails on a "no" answer when the input *is* interactive). Wrapped in
# retry(): its own generated/code clear can hit the same startup race as
# module:enable/setup:upgrade (found live); a failed attempt changes
# nothing (config, tables and vendor/ all still there), so retrying is safe.
retry compose exec -T -u www-data magento bin/magento module:uninstall --remove-data -n BillMySales_Magento >> "${E2E}/stack.log" 2>&1
CONFIG_LEFT="$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT COUNT(*) FROM core_config_data WHERE path LIKE 'billmysales/%';" 2>> "${E2E}/stack.log" | tr -d '\r')"
expect "${CONFIG_LEFT}" 0 "settings removed"
TABLES_LEFT="$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='magento' AND table_name LIKE 'billmysales_%';" 2>> "${E2E}/stack.log" | tr -d '\r')"
expect "${TABLES_LEFT}" 0 "the module's tables are gone"
if compose exec -T -u www-data magento sh -c 'test ! -e vendor/billmysales/billmysales-plugin-magento'; then
    pass "the module's code is gone (composer remove)"
else
    fail "vendor/billmysales/billmysales-plugin-magento is still there"
fi

# --- Result ------------------------------------------------------------------------

if [ "${FAILED}" = 0 ]; then
    say "All end-to-end cases passed"
else
    say "Some end-to-end cases FAILED"
fi
exit "${FAILED}"
