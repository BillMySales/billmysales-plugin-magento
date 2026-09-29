#!/usr/bin/env bash
#
# BillMySales for Magento: install/uninstall smoke test (make install-test).
#
# Separate from and much smaller than the end-to-end suite (tests/e2e/run.sh,
# which installs through Composer): this checks the module's actual current
# distribution channel, the built zip extracted into app/code by hand (no
# Composer, no mount), still installs cleanly and can be removed. It does
# NOT place an order or check a delivery; tests/e2e/run.sh already covers
# the functional behaviour, installed through Composer.
#
# bin/magento module:uninstall only proceeds for a module Composer
# installed: refusing an app/code one outright, or, with --non-composer,
# only reverting data patches (checked in Magento's own source; see
# tests/e2e/run.sh's install_plugin() for the Composer path this avoids
# for the main suite). So uninstalling here runs the module's own
# Setup\Uninstall directly (tests/e2e/fixtures.php's uninstall-module
# command, the same class the real command would call), then disables it,
# removes its code and re-runs setup:upgrade.
#
# Needs only Docker on the host. The stack's development ports must be
# free: stop the Magento development stack. E2E_KEEP=1 keeps the stack
# running (then make install-test-clean).
#
# Environment: TOOLS_IMAGE (set by the Makefile), STACK_REPO, STACK_REF
# (default master), E2E_KEEP, E2E_STACK_ENV (extra "NAME=value" lines, one
# per line, appended to the stack's .env).

set -euo pipefail

PLATFORM=magento
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
E2E="${ROOT}/var/install-test"
STACK="${E2E}/stack"
PROJECT="${PLATFORM}-install-test"
STACK_REPO="${STACK_REPO:-https://github.com/BillMySales/billmysales-docker-${PLATFORM}.git}"
STACK_REF="${STACK_REF:-master}"
TOOLS_IMAGE="${TOOLS_IMAGE:?Run it with make install-test}"
VERSION="$(sed -n 's/^ *"version": *"\(.*\)",\{0,1\}$/\1/p' "${ROOT}/plugin/composer.json")"
ZIP="${ROOT}/dist/billmysales-${PLATFORM}-${VERSION}.zip"
FAILED=0

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
fail() { printf '    ✘ %s\n' "$*"; FAILED=1; }
pass() { printf '    ✔ %s\n' "$*"; }
expect() { if [ "$1" = "$2" ]; then pass "$3"; else fail "$3 (got \"$1\", expected \"$2\")"; fi; }

compose() { (cd "${STACK}" && docker compose "$@"); }
port_in_use() { (exec 3<> "/dev/tcp/127.0.0.1/$1") 2> /dev/null; }
env_value() { { printf '%s\n' "${E2E_STACK_ENV:-}"; cat "${STACK}/.env.dev.example"; } | sed -n "s/^$1=//p" | head -1; }

# Retries a command a few times: the same startup race tests/e2e/run.sh
# documents (module:enable/setup:upgrade/module:uninstall regenerating
# classes into generated/code while something else clears it).
retry() { local i; for i in 1 2 3; do "$@" && return 0; sleep 5; done; return 1; }

up_and_wait() {
    compose up -d >> "${E2E}/stack.log" 2>&1
    local i=0
    for service in db opensearch magento caddy; do
        i=0
        while [ "$(compose ps "${service}" --format '{{.Health}}')" != healthy ]; do
            i=$((i + 1))
            [ "${i}" -ge 60 ] && die "${service} never became healthy (var/install-test/stack.log)"
            sleep 2
        done
    done
    i=0
    while [ "$(compose ps -a setup --format '{{.State}}')" != exited ]; do
        i=$((i + 1))
        [ "${i}" -ge 60 ] && die "setup never finished (var/install-test/stack.log)"
        sleep 2
    done
}

# shellcheck disable=SC2329 # called by the EXIT trap
cleanup() {
    local status=$?
    if [ "${E2E_KEEP:-0}" = 1 ]; then
        say "Stack kept running (E2E_KEEP=1), results in var/install-test; remove with: make install-test-clean"
        return
    fi
    say "Removing the stack; results kept in var/install-test/stack.log"
    [ -f "${STACK}/compose.yaml" ] && compose down -v --remove-orphans > /dev/null 2>&1 || true
    rm -rf "${STACK}" "${E2E}/zip-extract"
    exit "${status}"
}

# --- Preparation ---------------------------------------------------------------

[ -f "${ZIP}" ] || die "${ZIP} not found (make install-test builds it)"
if docker ps -aq --filter "label=com.docker.compose.project=${PROJECT}" | grep . > /dev/null; then
    die "the stack of a previous run is still there: make install-test-clean"
fi

rm -rf "${E2E}"
mkdir -p "${E2E}"
trap cleanup EXIT

say "Cloning ${STACK_REPO} (${STACK_REF})"
git clone -q --depth 1 --branch "${STACK_REF}" "${STACK_REPO}" "${STACK}"
for port in "$(env_value HTTP_PORT)" "$(env_value HTTPS_PORT)" "$(env_value MAILPIT_PORT)"; do
    port_in_use "${port}" && die "port ${port} is in use: stop the ${PLATFORM} development stack (docker compose down)"
done

say "Starting the stack (log: var/install-test/stack.log)"
{
    cat "${STACK}/.env.dev.example"
    if [ -n "${E2E_STACK_ENV:-}" ]; then
        printf '%s\n' "${E2E_STACK_ENV}"
    fi
    echo "COMPOSE_PROJECT_NAME=${PROJECT}"
} > "${STACK}/.env"
up_and_wait

say "Installing $(basename "${ZIP}") into app/code (no mount, no Composer)"
rm -rf "${E2E}/zip-extract"
mkdir -p "${E2E}/zip-extract"
unzip -q "${ZIP}" -d "${E2E}/zip-extract"
# "compose cp" needs the destination's parent directory to already exist
# (a fresh install has no app/code/BillMySales at all).
compose exec -T -u www-data magento mkdir -p /var/www/magento/app/code/BillMySales
compose cp "${E2E}/zip-extract/vendor/billmysales/billmysales-plugin-magento" magento:/var/www/magento/app/code/BillMySales/Magento
compose exec -T -u root magento chown -R www-data:www-data /var/www/magento/app/code/BillMySales
# Enabling it (and, in production mode, its own static content deploy) is
# the stack's own job (scripts/setup.sh, MAGE_ENABLE_MODULES): let it,
# exactly as a real merchant relying on this stack would, instead of
# re-deriving those same steps here (module:enable, setup:upgrade,
# static-content:deploy, cache:flush, and restarting "magento"/"cron" so
# PHP-FPM's opcache stops serving requests as if the code didn't exist).
echo "MAGE_ENABLE_MODULES=BillMySales_Magento" >> "${STACK}/.env"
enabled=0
for i in 1 2 3; do
    up_and_wait
    if [ "$(compose ps -a setup --format '{{.ExitCode}}')" = 0 ]; then enabled=1; break; fi
    sleep 5
done
[ "${enabled}" = 1 ] || die "setup never enabled the module (var/install-test/stack.log)"

say "Checking the install"
expect "$(compose exec -T -u www-data magento bin/magento module:status BillMySales_Magento 2>> "${E2E}/stack.log" | tr -d '\r')" \
    "BillMySales_Magento : Module is enabled" "module enabled"

say "Uninstalling"
compose cp "${ROOT}/tests/e2e/fixtures.php" magento:/var/www/magento/e2e-fixtures.php
compose exec -T -u www-data magento php e2e-fixtures.php uninstall-module >> "${E2E}/stack.log" 2>&1
# Wrapped in retry(): its own generated/code clear can hit the same startup
# race as module:enable/setup:upgrade (found live, "Directory not empty");
# a failed attempt changes nothing (the module is still enabled, config and
# code still there), so retrying is safe.
retry compose exec -T -u www-data magento bin/magento module:disable BillMySales_Magento >> "${E2E}/stack.log" 2>&1
compose exec -T -u root magento rm -rf /var/www/magento/app/code/BillMySales/Magento
{
    retry compose exec -T -u www-data magento bin/magento setup:upgrade
    compose exec -T -u www-data magento bin/magento cache:flush
} >> "${E2E}/stack.log" 2>&1

say "Checking the uninstall"
CONFIG_LEFT="$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT COUNT(*) FROM core_config_data WHERE path LIKE 'billmysales/%';" 2>> "${E2E}/stack.log" | tr -d '\r')"
expect "${CONFIG_LEFT}" 0 "settings removed"
TABLES_LEFT="$(compose exec -T db mariadb -umagento -p"$(env_value DB_PASSWORD)" magento -N \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='magento' AND table_name LIKE 'billmysales_%';" 2>> "${E2E}/stack.log" | tr -d '\r')"
expect "${TABLES_LEFT}" 0 "the module's tables are gone"
if compose exec -T -u www-data magento sh -c 'test ! -e app/code/BillMySales/Magento'; then
    pass "the module's code is gone"
else
    fail "app/code/BillMySales/Magento is still there"
fi

# --- Result ------------------------------------------------------------------------

if [ "${FAILED}" = 0 ]; then
    say "Install/uninstall check passed"
else
    say "Install/uninstall check FAILED"
fi
exit "${FAILED}"
