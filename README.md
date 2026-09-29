BillMySales for Magento
========================

Magento 2 / Mage-OS module that sends orders to [BillMySales](https://www.billmysales.com)
when they reach a status you choose (BillMySales then issues the billing
document).

- Deliveries are queued when an order reaches a selected status (or the
  merchant asks to send it again), never sent from that same request: the
  order page never waits for BillMySales. They are sent by the module's own
  cron job (`billmysales_process_due_deliveries`, every 5 minutes). Failed
  deliveries are retried on network errors, timeouts, rate limits and server
  errors (after 1 min, 5 min, 30 min, 2 h and 12 h); other HTTP errors are
  logged, not retried. BillMySales is idempotent, so a repeated delivery is
  harmless.
- Each notification is signed with HMAC-SHA256 with the secret shared with
  BillMySales.
- Each delivery attempt (sent, retry scheduled, or given up) is recorded as
  a private note in the order's own status history (Magento's native
  "Notes History", admin-only). The order view page also shows a "Send to
  BillMySales" link to send it again.
- Admin-defined fields (Stores > Configuration > Sales > BillMySales >
  Checkout fields) are added to the checkout's shipping address form and
  sent with the order.

Requirements: Mage-OS 3.5.0 (Magento 2.4.9), PHP 8.4 or 8.5. Tested (end to
end): Mage-OS 3.5.0 with PHP 8.5, the only combination the Mage-OS Docker
stack validates.

Installation
------------

1. Download `billmysales-magento-<version>.zip` from the
   [releases](https://github.com/BillMySales/billmysales-plugin-magento/releases)
   (not the repository's own zip). It contains the module at the path a
   `composer require` would install it
   (`vendor/billmysales/billmysales-plugin-magento`).
2. Extract the zip's `vendor/billmysales/billmysales-plugin-magento` folder
   into your Magento installation's `app/code/BillMySales/Magento`.
3. `bin/magento module:enable BillMySales_Magento`
4. `bin/magento setup:upgrade`
5. In production mode: `bin/magento setup:static-content:deploy -f` (it
   doesn't generate static files on demand, and `setup:upgrade` clears the
   previously deployed ones) and restart PHP-FPM (`opcache.validate_timestamps=0`,
   Magento's own recommended production setting, keeps serving requests as
   if the module's files didn't exist otherwise; found installing it on
   this project's own Magento stack, whose default is production mode).
6. Stores > Configuration > Sales > BillMySales: the notification URL and
   the secret given by BillMySales, and the order statuses that notify.

A Magento project managed entirely through Composer can instead add the
extracted folder as a [path repository](https://getcomposer.org/doc/05-repositories.md#path)
and `composer require billmysales/billmysales-plugin-magento`, which also
enables the module and runs `setup:upgrade` (Magento's own Composer plugin).

Notification
------------

A `POST` to the configured URL, with the order's own official
representation (the same shape `GET /V1/orders/<id>` returns) plus its
shipping address:

```json
{
  "entity_id": 7,
  "increment_id": "000000007",
  "status": "processing",
  "state": "processing",
  "grand_total": 11900,
  "customer_email": "buyer@example.com",
  "items": [ ],
  "billing_address": { },
  "extension_attributes": { },
  "shipping_address": { },
  "checkout_fields": { "rut": "11.111.111-1" }
}
```

Headers:

| Header | Value |
|---|---|
| `X-BillMySales-Signature` | base64 of the HMAC-SHA256 of the raw body with the secret |
| `X-BillMySales-Platform` | `magento` |
| `X-BillMySales-Platform-Version` | Magento version |
| `X-BillMySales-Plugin-Version` | module version |
| `X-BillMySales-Source` | store base URL |
| `X-BillMySales-Event` | `order.status_changed` or `order.resent` ("Send to BillMySales") |
| `X-BillMySales-Delivery` | UUID of the notification (the same on retries) |
| `User-Agent` | `BillMySales-magento/<version>` |
| `X-MagentoBMS-Hmac-Sha256` | the same signature, as the module's first, unfinished version already named it |

Verifying the signature (Python):

```python
expected = base64.b64encode(hmac.new(secret, body, hashlib.sha256).digest()).decode()
hmac.compare_digest(expected, headers["X-BillMySales-Signature"])
```

Development
-----------

Layout: `plugin/` is the module (what the zip installs, at
`app/code/BillMySales/Magento`); the repository root has the development
tools.

```
plugin/
  Webhook/              Delivery, Payload, Headers: builds and sends a notification
  Model/ResourceModel/   DeliveryQueue, CheckoutFieldValue (own tables, ResourceConnection)
  Observer/              queues a delivery on sales_order_save_commit_after
  Cron/                  the scheduled job that sends due deliveries
  Controller/Adminhtml/  the "Send to BillMySales" action
  Block/Adminhtml/       the order view page's status block, the secret's and checkout fields' admin editors
  Checkout/              the admin-defined checkout field definitions (settings storage)
  Plugin/Checkout/       adds the fields to the checkout, and saves what the customer submits
  Setup/                 uninstall (drops the tables and the settings)
  Model/Config/Source/   the order statuses multiselect
  Model/Config/Backend/  sanitizes the checkout field definitions before they're saved
  etc/                   module.xml, db_schema.xml, events.xml, crontab.xml, acl.xml, system.xml, di.xml, extension_attributes.xml
  view/adminhtml/        the order view page's template and layout, the secret's and checkout fields' admin JS
  view/frontend/         combines the checkout fields into their extension attribute before the address is saved
  i18n/                  es_ES, es_CL catalogs (Magento reads these CSV files directly)
tests/src/               PHPUnit unit tests
tests/fixtures/          minimal doubles of the Magento classes the tests mock (no full framework install)
scripts/i18n.php         extracts the source phrases and updates plugin/i18n/*.csv
docker/Dockerfile        tools image (PHP CLI, Composer, Xdebug)
```

Every task runs in Docker containers (the host's PHP and Node.js aren't
used): PHP 8.4, the lowest the plugin supports (`PHP_VERSION=8.5` runs them
on another one), and ESLint in `node:24-alpine`:

```shell
make install        # composer install (dev tools) and npm ci
make lint           # PHP CS Fixer (PSR-12), dry run; Magento2 coding standard + required docblocks (phpcs.xml); ESLint
make fix            # PHP CS Fixer's and ESLint's fixes (style)
make analyse        # PHPStan (iodigital-com/phpstan-magento2-stubs, PHP 8.4)
make test           # PHPUnit, with coverage (var/tests-coverage.txt): 100% of plugin
make i18n           # updates plugin/i18n/*.csv from the source phrases (scripts/i18n.php)
make check          # lint + analyse + test + version consistency
make build          # dist/billmysales-magento-<version>.zip
make clean
```

The version lives in the module's own metadata (`plugin/composer.json`'s
`version`, matching `etc/module.xml`'s `setup_version`); `CHANGELOG.md` must
match it (`make check`).

### End-to-end tests

```shell
make e2e            # E2E_KEEP=1 keeps the stack running (then make e2e-clean)
make install-test   # separate, much smaller: install-test-clean to remove
```

`tests/e2e/run.sh` clones the
[Mage-OS Docker stack](https://github.com/BillMySales/billmysales-docker-magento)
into `var/e2e/stack` (`STACK_REPO`, `STACK_REF`, default `master`), starts
it, installs the module the way a merchant using Composer would (a "path"
repository pointing at `plugin/`, required as a normal dependency:
`install_plugin()` copies the source under `/var/www/magento` — not
`/tmp`: static content deploy resolves a view file's real, symlink-
followed path and refuses one outside the project root — configures the
repository and runs `composer require`, `module:enable`, `setup:upgrade`;
in production mode, this stack's default, `setup:upgrade` also clears
`pub/static`, so admin pages need it redeployed, and its own cache clear
can leave the "config" cache stale enough that the module's own
`etc/events.xml` observer never fires, needing an explicit `cache:flush`;
and this image's `php.ini` has `opcache.validate_timestamps=Off`, so
PHP-FPM already running when the code was added needs restarting, same as
a real deployment would) and a webhook receiver on port 8099
(`tests/e2e/receiver.php`, standing in for BillMySales: it stores each
request as received and answers the status a case asks for), runs the
cases and removes the stack and the receiver. It needs only Docker. **The
stack's development ports (8103, 8403, 8025) and 8099 must be free: stop
the Magento development stack first** (the script checks them before
starting; `E2E_RECEIVER_PORT` picks another port for the receiver, for a
concurrent run). `E2E_STACK_ENV` (extra `NAME=value` lines, one per line,
appended to the stack's `.env`) can override any other stack variable, but
this stack validates only one `MAGEOS_VERSION`/`PHP_VERSION` combination
(see the stack's own README): changing them isn't validated.

Each case does something in Magento and `tests/e2e/check.php` checks what
reached the receiver: the number of requests, the signature (recomputed
from the raw body with the secret, in `X-BillMySales-Signature` and
`X-MagentoBMS-Hmac-Sha256`), the headers (platform, versions, event,
source, delivery UUID, User-Agent, the secret in none), the payload against
the order's JSON Schema (`tests/e2e/order-schema.json`), and the order's
id, status and total. The last case runs the real
`bin/magento module:uninstall --remove-data`, since installing through
Composer (even a local path) makes the module one it recognizes.

The deliveries stay in `var/e2e/webhooks` until the next run or `make
clean`: `<case>-<time>.body` is the raw body, `.json` has the headers, the
decoded payload and the status the receiver answered.

A separate, much smaller check, `tests/install/run.sh`
(`make install-test`, its own stack clone in `var/install-test`): the
module's actual current distribution channel, the built zip extracted
into `app/code` by hand (no mount, no Composer) — only that it installs
and can be removed, no order or delivery. `bin/magento module:uninstall`
only proceeds for a module Composer installed, otherwise refusing (or,
with `--non-composer`, only reverting data patches, not removing config or
tables) — checked in its source (`Magento\Setup\Console\Command\
ModuleUninstallCommand::validate()`) — so this instead runs the module's
own `Setup\Uninstall` directly (`tests/e2e/fixtures.php`'s
`uninstall-module` command), then disables the module, removes its code
and re-runs `setup:upgrade`, the same steps the real command takes
internally.

### Releases

Bump the version (`plugin/composer.json`, matching `etc/module.xml`'s
`setup_version`), add its `CHANGELOG.md` entry, commit and push a
`vX.Y.Z` tag. The release workflow (`.github/workflows/release.yml`) calls
the tests (`ci.yml`, PHP 8.4 and 8.5) and then the end-to-end tests
(`e2e.yml`: both its `e2e` and `install-test` jobs), and only when both
passed checks the tag matches the version, runs `make build` and publishes
the GitHub Release with the zip. `ci.yml` and `e2e.yml` also run on every
push and pull request (the end-to-end deliveries are uploaded as the
`e2e-results` artifact, the install/uninstall stack log as
`install-test-results`). Dependabot (`.github/dependabot.yml`) opens
weekly pull requests for the Composer and
npm tools and the workflows' actions.

Platform decisions
-------------------

Recorded here (not compared against anything else) so the reasoning behind
each choice stays with the code:

- **License**: the GNU General Public License v3.0 or later
  (GPL-3.0-or-later). Adobe Commerce Marketplace's allowed license list
  (AFL-3.0, Apache-2.0, BSD-2-Clause, GPL-3.0, LGPL-3.0, MIT, MPL-1.1,
  OSL-3.0, Custom) doesn't include AGPL-3.0; GPL-3.0-or-later is the
  strongest copyleft option it does allow.
- **Minimum version**: Mage-OS 3.5.0 (Magento 2.4.9), the version this
  project's own Magento stack runs (fully open, no Adobe keys needed); a
  full Magento install is heavy enough that the stack validates only this
  one version, not a matrix, so it's also the only one the module
  supports. Magento 2.4.9 itself documents PHP 8.4 or 8.5 (8.3 for
  upgrades only, so PHP 8.4 is the module's own floor); the end-to-end
  tests run the stack's own PHP, 8.5. The toolchain (PHP CS Fixer,
  PHPStan, PHPUnit) runs both, 8.4 to 8.5, so it's checked on every PHP
  Magento 2.4.9 documents for a new install, not only the one the
  end-to-end tests themselves exercise against a real Magento.
- **PHPStan stubs**: `iodigital-com/phpstan-magento2-stubs` ships stub
  files only for a curated set of classes PHPStan's own reflection
  struggles with; it isn't a full Magento framework install. The rest of
  the framework classes the plugin references are declared, minimally
  (only the members the plugin calls), in `tests/fixtures/classes.php`,
  required by both PHPUnit's bootstrap (as real doubles, mocked with
  Mockery) and PHPStan's (`bootstrapFiles`), so the same minimal surface
  serves both tools without a full Magento install.
- **Own queue table and Magento's own cron**: the module keeps its own
  table (`billmysales_delivery_queue`, one row per pending delivery,
  removed once it's no longer pending), drained by a job the module
  registers in `etc/crontab.xml`. Magento's own Message Queue framework was
  considered instead, but it needs a separately-supervised consumer process
  (`bin/magento queue:consumers:start`, its own process manager entry)
  beyond what a store's cron already runs for every other scheduled task; a
  plain declarative-schema table plus a cron job needs nothing extra to
  operate. An order reaching a selected status, or a manual resend, only
  ever queues a delivery, never sends it in the same request.
- **Delivery attempts as native order notes, not an own table**: each
  attempt is recorded with `Order::addStatusHistoryComment()`, Magento's
  own per-record note mechanism (`vendor/mage-os/module-sales/Model/Order.php`),
  called with its default `$isVisibleOnFront = false` (admin-only, never
  shown to the customer) and no `$status` argument (the order's current
  status is left unchanged). The admin already renders every status
  history comment in the order view page's own "Notes History" tab
  (`Magento\Sales\Block\Adminhtml\Order\View\History`, wired in
  `vendor/mage-os/module-sales/view/adminhtml/layout/sales_order_view.xml`),
  so the module needs no block or template of its own for this, nor an
  own table to keep the attempts in.
- **`sales_order_save_commit_after`, not `sales_order_save_after`**: fires
  only once the order's database transaction actually commits, so a
  delivery is never queued for a status change a later error in the same
  request rolls back.
- **Payload format**: the order's own official representation, built
  through `Magento\Framework\Reflection\DataObjectProcessor`, the class
  Magento's own webapi layer uses to serialize a data interface object
  (`Magento\WebapiAsync\Controller\Rest\AsynchronousRequestProcessor`, for
  one) into the exact shape `GET /V1/orders/<id>` returns, rather than a
  hand-picked field list that could drift from it. The shipping address
  isn't part of `OrderInterface` (only the billing one is): added the same
  way, from the order's own `getShippingAddress()`.
- **The secret shown as a password field with a show button**: `system.xml`'s
  `type="obscure"` field (`Magento\Config\Model\Config\Backend\Encrypted`'s
  frontend) has no show/hide button (`Obscure` extends `Password`, always
  masking with `******`); `Block/Adminhtml/System/Config/Secret.php`
  overrides `_getElementHtml()` to render a plain password field plus a
  button (`view/adminhtml/web/js/secret-toggle.js`, loaded via
  `data-mage-init`, no bundler) that toggles it, matching the convention
  without a built-in field type that already does it.
- **Settings keys changed**: no migration from the module's first,
  unfinished version is offered (it never shipped a working configuration);
  a fresh `setup:upgrade` on this version configures it from scratch.
- **Checkout fields as one extension attribute, not address custom
  attributes**: address custom attributes
  (`Magento\Quote\Api\Data\AddressInterface`'s `CustomAttributesDataInterface`)
  looked generic enough at first, but `Magento\Quote\Model\Quote\Address`
  (a real, EAV-backed model, not a plain data-transfer object) filters
  `custom_attributes` down to `getCustomAttributesCodes()`
  (`AbstractAddress::attributeList`, real `customer_address` EAV attribute
  codes) before a plugin ever sees them: an admin-defined field's code,
  not being a real EAV attribute, was silently dropped, confirmed with a
  live checkout (the field showed and submitted correctly; the value
  never reached the plugin). Making it a real EAV attribute was rejected:
  those are for fields a module declares once at install time (like core's
  own `vat_id`), not ones a merchant defines afterwards through a settings
  page, and every address in the system would carry it (customer address
  book, admin address forms), not just the checkout submission. Extension
  attributes (a *different* mechanism, generic for any entity regardless
  of EAV) don't have this problem, confirmed the same way: one is declared
  on `Magento\Checkout\Api\Data\ShippingInformationInterface` (not the
  address) as a single JSON string,
  `billmysales_checkout_fields` (`etc/extension_attributes.xml`), the same
  extension point `Magento_Checkout/js/model/shipping-save-processor/payload-extender`
  exists for. `Plugin\Checkout\LayoutProcessorPlugin` (an `afterProcess()`
  plugin on `Magento\Checkout\Block\Checkout\LayoutProcessor`,
  `etc/frontend/di.xml`) adds each admin-defined field to the shipping
  address form with its own scratch `dataScope`
  (`shippingAddress.billmysales_fields.<key>`, not a real address
  attribute); `view/frontend/web/js/collect-checkout-fields.js` (a
  `payload-extender` mixin, `view/frontend/requirejs-config.js`) combines
  them into the one extension attribute before the address is saved.
  Read back with `Plugin\Checkout\SaveCheckoutFieldsPlugin` (a
  `beforeSaveAddressInformation()` plugin on
  `Magento\Checkout\Model\ShippingInformationManagement`, `etc/di.xml`:
  global, not `etc/frontend`, since this service runs in the webapi_rest
  area, which guest checkout also delegates to with the cart's real id
  already resolved) and stored in `billmysales_checkout_field_value`,
  keyed by the quote (not the order, which doesn't exist yet at that
  point): the payload reads it by the order's own `getQuoteId()`. Only the
  shipping address form is covered (not a separate billing address form for
  virtual-only carts), matching the single address most checkouts show.
- **Field definitions as one JSON configuration value, no admin grid**: an
  admin grid/listing (UI Component, its own controller CRUD) would be
  disproportionate for a handful of merchant-defined fields; the
  definitions are one `core_config_data` value (`Model\Config\Backend\CheckoutFields`
  sanitizes it on save: rows without a label are dropped, and the key is
  always derived from the label, unique within the list), edited through a
  row editor (`Block\Adminhtml\System\Config\CheckoutFields`, a hidden
  input plus `view/adminhtml/web/js/checkout-fields-editor.js` rendering
  the rows and keeping the hidden input's JSON in sync) in place of
  `system.xml`'s plain field types.

License
-------

Copyright (c) 2026 BillMySales. Licensed under the
[GNU General Public License v3.0 or later](LICENSE) (GPL-3.0-or-later).
