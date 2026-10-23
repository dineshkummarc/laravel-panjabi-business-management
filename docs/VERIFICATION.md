# Verification evidence

Recorded on 23 October 2026 (Asia/Dhaka). This document distinguishes completed checks from unperformed validation.

## Completed

| Check | Result |
| --- | --- |
| SQLite feature/service suite | 29 tests passed; 139 assertions |
| PostgreSQL 16 feature/service suite | 29 tests passed; 139 assertions |
| Code style | `vendor/bin/pint --test` passed after formatting |
| PHP syntax checks | Application/config/database/route PHP files parsed successfully |
| Blade compilation | `php artisan view:cache` succeeded |
| Frontend production build | `npm run build` succeeded |
| Composer runtime extensions | `composer check-platform-reqs` succeeded on PHP 8.5.4 |
| Dependency installation | Laravel 12 / Filament 5 / seven requested packages resolved and locked |
| Demo setup | Migrations and idempotent guarded demo seed command executed |
| Browser inspection | Public landing, owner sign-in/dashboard, storefront and operations/Filament screens rendered |
| README screenshots | Actual running app captures saved in `docs/images/` |
| Bengali PDF | Generated through the requested package, rendered with Poppler, and visually inspected |

Machine-readable test outputs: `test-results-sqlite.json`, `test-results-postgresql.json`.

## Suite coverage

Checkout, fulfillment, split payments, partial returns, journal balancing, duplicate checkout/payment protection, oversell rollback, cancellation reversal, partial purchase receipts, supplier payments, transfer conservation, module dependencies, tenant/branch tampering, cross-business foreign-key validation, no-context access, tailoring collection/handover, repeated production/payroll posting, HTTP storefront checkout/tracking, role page rendering, AI review, real PDF output, CSV validation, unsafe source targets, simulated courier clearing/remittance, simulated attendance deduplication, cashier shifts, signed gateway callbacks, failed payment attempts, and language switching.

## Findings fixed during verification

- Selected Laravel 12 for the requested package compatibility range.
- Disabled public/unscoped package interfaces and moved the secondary admin package away from Filament's `/admin` routes.
- Replaced package dashboard sample metrics with tenant-scoped data.
- Configured AI Catalog taxonomy against the actual application schema; its default `parent_id` expectation otherwise caused a PostgreSQL transaction error.
- Bundled fonts and wrapped native PDF output to avoid request-time font downloads.
- Associated cash collection/refund records with shifts rather than recomputing all sale cash by broad date ranges.

## Still unverified

- Dedicated simultaneous multi-process last-unit/concurrent-checkout stress tests and production load tests.
- Real payment-provider settlement/refunds and real courier callbacks.
- Physical attendance hardware, firmware/protocol variation, or biometric workflows.
- Live AI provider credentials/model behavior, full cost controls, and hostile DNS/redirect testing.
- MySQL compatibility, offline POS, real printer/cash drawer control.
- Full automated browser journey suite, complete keyboard/accessibility audit and full Bengali translation coverage.
- Backup/restore drill, public hosting, disaster recovery, and statutory tax/accounting correctness.

Passing the suite is evidence for the tested scenarios; it is not a production/security/compliance certification.
