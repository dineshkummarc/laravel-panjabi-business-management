# Implementation status

The complete long-term scope is in `PRODUCT_SPECIFICATION.md`. The repository now contains functioning end-to-end core workflows, but that broader specification is not fully implemented.

## Implemented and exercised

- [x] Laravel 12 / Filament 5 and all seven requested packages installed with lockfiles.
- [x] Organization/branch data model, role gates, tenant scope and selected branch restrictions.
- [x] Registration presets, owner staff creation, platform overview, protected shared-demo configuration.
- [x] Catalog/categories/variants/media, business-scoped slugs, reviewed CSV import/export.
- [x] Available-stock and movement services, opening adjustments, partial branch transfers.
- [x] Public pages, storefront collection/products, cart, guest COD checkout, private tracking.
- [x] Customer signup/history, wishlist, change requests, branch pages and basic campaign landing pages.
- [x] Order transitions, manual/split payments, cancellation, partial line returns and ledger reversal.
- [x] POS search/scanner input, server totals, cash shifts and printable/Unicode invoices.
- [x] Basic supplier purchasing/partial receipt/payment; basic wholesale pricing/dues.
- [x] Basic tailoring stages/deposits/handover and basic production/material consumption.
- [x] Employees/manual attendance, idempotent monthly salary payment.
- [x] Balanced posting service, expenses, date/branch-filtered trial balance.
- [x] Application-owned AI review, package-backed AI/attendance simulations, courier/COD and signed-payment simulations.
- [x] Common Bengali UI labels and Unicode content/PDF support.
- [x] Fictional multi-business demo data and role accounts.
- [x] SQLite and PostgreSQL suites; 29 tests / 139 assertions on each.
- [x] GitHub showcase README with actual app screenshots and explicit estimate assumptions.

## Outstanding required/advanced work from the original specification

- [ ] Membership-based multi-business/multi-role permissions; 2FA/email verification and complete audit coverage.
- [ ] Complete module registry enforcement for routes/jobs/exports, branch overrides and formal drain policies.
- [ ] Warehouses/bins, reservations/expiry, fractional units, stocktakes, revaluation/FIFO/weighted-average alternatives.
- [ ] Multi-line purchase documents, landed costs, purchase returns and supplier statements.
- [ ] Rich product attributes, bundles, size chart management, scheduled pricing/coupons, barcode-label printing.
- [ ] Linked exchanges, partial shipments, preorders/backorders and return-to-origin resolution.
- [ ] Dealer tiers/credit limits, quotation pipeline and wholesale dispatch schedules.
- [ ] Complete tailoring measurement history, fabric ledgers, alterations and assignments.
- [ ] BOMs, multi-stage production/WIP, wastage, QC, subcontracting and detailed cost allocation.
- [ ] Financial statements, configurable chart of accounts, fiscal locks, taxes/VAT, reconciliation and statutory review.
- [ ] Leave/shifts/overtime, payroll approvals/payslips and actual attendance-device mapping/sync.
- [ ] Loyalty/vouchers/referrals, consent-based campaigns and actual email/SMS business notifications.
- [ ] Live merchant payment/refund and courier integrations, verified signed webhooks and reconciliation.
- [ ] Per-tenant AI keys/budgets, live-provider verification and broader source-import hardening.
- [ ] Custom domains, full SEO/sitemaps/structured feeds, complete localization and accessible browser tests.
- [ ] Optional SaaS billing, subscriptions/entitlements and grace-period behavior.
- [ ] Concurrent/load tests, production deployment validation, backup/restore drill, documented recovery objectives.

See README feature sections for the exact boundaries of each working module. Do not remove these outstanding items merely because a related CRUD screen exists.
