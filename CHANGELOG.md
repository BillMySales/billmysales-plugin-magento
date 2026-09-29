# Changelog

All notable changes to this module. Versions follow [Semantic Versioning](https://semver.org).

## [2.0.0] - 2026-09-28

Rewritten:

- Deliveries are queued when an order reaches a selected status (or the
  merchant asks to send it again), and sent from the module's own cron job
  (`billmysales_process_due_deliveries`, every 5 minutes): a status change
  never waits for BillMySales. Failed deliveries are retried on network
  errors, timeouts, rate limits and server errors (after 1 min, 5 min,
  30 min, 2 h and 12 h); other HTTP errors are logged, not retried.
- Standard `X-BillMySales-*` headers, plus the legacy
  `X-MagentoBMS-Hmac-Sha256` signature header the first, unfinished module
  already named.
- Each delivery attempt is recorded as a private note in the order's own
  status history (Magento's native "Notes History", admin-only); the order
  view page also shows a "Send to BillMySales" link to send it again.
- Admin-defined checkout fields (Stores > Configuration > Sales >
  BillMySales > Checkout fields): added to the checkout's shipping address
  form and sent with the order.
- English source strings, with Spanish translation catalogs (`es_CL`,
  `es_ES`).
- Settings stored in new `billmysales/*` configuration values (reconfigure
  after the update).
- Requires Mage-OS 3.5.0 (Magento 2.4.9), PHP 8.4 or 8.5.

## [1.0.0] - 2022-01-26

Incomplete first version: only `registration.php` and an observer, no
`etc/module.xml` (Magento couldn't enable it).
