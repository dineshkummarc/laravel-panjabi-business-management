# Panjabi Business — Complete AI Coding Agent Prompt

Copy this document into your AI coding agent, or instruct it to implement this repository according to this README.

## Your assignment

Act as a senior Laravel engineer, product designer, and QA engineer. Build a complete, runnable, end-to-end business management application for Bangladeshi Panjabi businesses using PHP, Laravel, and Filament. Deliver working code, migrations, seeders, public pages, business workflows, automated tests, and deployment documentation. Do not stop at planning, UI mockups, CRUD scaffolding, or a dashboard with fabricated metrics.

Support online sellers, Facebook commerce sellers, single shops, multi-branch retailers, wholesalers, distributors, manufacturers, tailoring businesses, and mixed businesses. A small shop must be able to start with POS and inventory and activate additional modules later. Provide sensible onboarding presets for each business type.

Work autonomously within this repository. Inspect existing files and instructions first; preserve useful work. Maintain an implementation checklist in `docs/IMPLEMENTATION_STATUS.md`. Implement and verify in vertical slices, continuing until all required workflows work. Make reasonable reversible decisions and document assumptions. Ask only for credentials or decisions that genuinely block progress. Never claim a live integration or completed feature without evidence.

## 1. Stack and package compatibility

Use mutually compatible, supported stable versions of PHP, Laravel, Filament, Livewire, Tailwind CSS, a production SQL database, and Redis for production queues/cache. SQLite may support a quick demo only where the actual queries remain portable. Commit dependency lockfiles. Provide a reproducible local environment and an optional Docker Compose setup.

Inspect the current Composer metadata, README, source, license, migrations, and requirements of each requested package before integrating. Do not invent facades, APIs, configuration keys, or commands:

| Package | Intended use |
| --- | --- |
| `imrandevbd/drutogoti` | Evaluate documented development/performance capabilities and apply where compatible. |
| `imrandevbd/laravel-admin-panel` | Evaluate dashboard/admin capabilities and integrate where compatible with the required Filament interface. |
| `imrandevbd/laravel-ai-catalog` | Draft product catalog content from supported sources, including permitted URLs and imports. |
| `imrandevbd/laravel-ai-hub` | Provider-independent AI features, configuration, usage tracking, and testing adapters. |
| `imrandevbd/laravel-attendance-hub` | Supported attendance-device ingestion and normalization. |
| `imrandevbd/laravel-unicode-pdf` | Bengali and English invoices, receipts, statements, and reports. |
| `imrandevbd/laravel-universal-slug` | Unicode-safe, scoped, unique multilingual URLs. |

Filament is the primary business administration UI. Do not assume `laravel-admin-panel` is a Filament plugin. Resolve overlapping panels, authentication, routes, assets, and permissions explicitly. If a package is unavailable, incompatible, or would undermine tenant isolation, document the precise issue and provide a working adapter or native implementation for the business capability. Do not remove Filament, force broken dependencies, or silently skip requirements. Keep a package compatibility and integration report in `docs/PACKAGES.md` with selected versions and verified usages.

## 2. Business architecture and isolation

Build a modular multi-business application: an organization/tenant represents one independent business; branches and warehouses belong to that organization. Support one-business installations without requiring SaaS billing. Platform subscription billing is an optional module.

Implement:

- Platform administrator panel; tenant business panel; customer storefront/account area; focused POS screen.
- Business registration/onboarding, business type preset, organization settings, logo, trade details, language, timezone, currency, branch creation, and first-user setup.
- Multiple branches, warehouses, stock locations, branch hours, contact details, invoice sequences, branch pricing overrides, and branch-specific staff assignments.
- Owner-wide consolidated views and branch-filtered operational views. Preserve the active branch in navigation and reports.
- Tenant and branch authorization enforced server-side in queries, policies, actions, services, exports, queued jobs, storage access, caches, notifications, and integrations. Never trust tenant or branch IDs submitted by the client.
- Membership-based roles and permissions: platform administrator, business owner, business administrator, branch manager, cashier, salesperson, inventory manager, purchasing officer, accountant, production supervisor, HR officer, and customer.
- An immutable audit trail for sensitive actions, including actor, tenant, branch, timestamp, reason, and meaningful changes. Hide secrets and sensitive personal data in logs.

Use a modular monolith with clear domain services and transactions. Avoid microservices and speculative abstraction. Model domain records explicitly instead of storing all business data in arbitrary JSON. Use money-safe arithmetic and precise decimal quantities; never binary floating-point arithmetic for financial calculations.

## 3. Module activation system

Build a real module registry and settings interface. Modules include storefront, online orders, POS, inventory, purchasing, wholesale, tailoring, production, accounting, CRM/loyalty, campaigns, courier, HR/payroll, attendance, AI, and optional SaaS subscriptions.

Each module declares dependencies, permissions, available routes, navigation, jobs, webhooks, and configuration requirements. Enable/disable modules per organization, with branch overrides only where operationally valid. Authorization and module checks must apply to direct URLs and API requests, not only navigation.

Explain dependency effects before changes. Do not allow an invalid state such as active POS without its inventory dependency. Disabling a module preserves records, allows authorized historical access, and prevents new operational work. Define a drain/read-only policy for pending payments, courier callbacks, reservations, and in-flight jobs so disabling a module cannot lose money or strand orders. Webhooks must still reconcile already initiated external operations safely.

Provide presets: online seller, retail shop, multi-branch retailer, wholesaler, manufacturer, and tailoring studio. Owners can customize presets without losing data.

## 4. Product catalog and pricing

Support products, categories, collections, brands, suppliers, media galleries, barcode/SKU, status, descriptions, care instructions, SEO fields, and localized content.

Panjabi-specific attributes include size, actual garment measurements, chest/length/sleeve/shoulder, fit, fabric, color, embroidery, collar, cuff, pattern, season, Eid collection, design code, and origin. Attributes must be configurable. Products may include pajama, waistcoat, accessories, bundles, and coordinated sets.

Use variant-level SKU/barcode, cost, selling price, wholesale tiers, reorder levels, weight, and stock. Support size charts, variant images, sale schedules, channel/branch prices, customer price groups, bundles, and configurable discount approval limits. Preserve line-item descriptions, prices, discounts, taxes, and cost snapshots on finalized transactions.

Implement validated CSV import/export with preview, row errors, duplicate handling, background processing, and sample files. Barcode label printing must work. Prevent deletion of referenced products; archive them instead.

## 5. Inventory, purchasing, and suppliers

Implement an auditable stock movement ledger by tenant, location, and variant. Stock on hand, reserved, available, damaged, and in-transit quantities must be consistently derived or reconciled. All changes pass through transactional inventory services with locking or equivalent concurrency protection.

Support opening stock, receipts, adjustments with reasons/approval, stocktakes, damage/write-off, purchase returns, reorder alerts, supplier management, purchase orders, partial goods receipts, bills, supplier payments/dues, landed-cost allocation, and supplier statements.

Branch transfers follow request → approve → dispatch → receive, with partial receipt, discrepancies, and cancellation rules. Stock in transit cannot be sold by either destination or source as ordinary available stock. Support configurable negative stock only with explicit permission and audit.

Define reservation expiry/release, order cancellation, returns, and bundle component consumption. Concurrent purchases of the final unit must not oversell. Provide reconciliation tools and tests proving stock conservation.

## 6. Public website and storefront

Build polished, responsive, accessible Bengali/English public pages using real seeded content and reusable components. Include:

- Platform/business-solution home, features overview, module details, industry/business-type pages, demo access, FAQ, contact, and optional pricing when SaaS billing is enabled.
- Tenant storefront home, product listing, search, category/collection pages, filters, sorting, product details, variant selection, size guide, stock availability, related products, wishlist, cart, and checkout.
- Campaign/Eid landing pages editable through safe content blocks, banners, featured collections, calls to action, and scheduled publishing.
- About, branch locator, delivery policy, returns/exchanges, privacy, terms, size guide, and contact pages.
- Customer registration/login, profile/address book, order history/details, secure order tracking, cancellation/return requests, and invoices.

Use tenant-aware routing with a documented local subdomain/path setup and optional verified custom domains. Public storefronts expose only their organization's published data. Keep shared SaaS marketing routes distinct from tenant storefront routes.

Support guest checkout, authenticated checkout, configurable delivery zones, Bangladesh district/upazila address data from a documented source or maintainable seed, delivery fees, coupons, and clear totals. Avoid mandatory postal codes where inappropriate. Normalize Bangladesh phone numbers. Currency defaults to BDT (`৳`); timezone defaults to `Asia/Dhaka`. Persist timestamps consistently and display in business timezone.

Search engine support includes canonical URLs, sitemap, robots configuration, product structured data, social previews, multilingual slugs, and redirects for changed slugs. Public tracking requires a secure token or equivalent verification; sequential order numbers alone must not expose personal data.

## 7. Orders, payments, fulfillment, and returns

Support orders from storefront, phone/manual entry, social channels, wholesale, and POS through shared domain services. Record channel, branch, customer, delivery address snapshot, items, payments, discounts, shipping, and notes.

Use separate order, payment, fulfillment, and return status machines. Explicitly define legal transitions and permissions in documentation and code. Include draft/pending, awaiting verification, confirmed, processing, packed, shipped, delivered, completed, canceled, failed delivery, and returned where appropriate. Avoid one status field that conflates payment with shipping.

Provide pending-order queues, staff assignment, search/filter, bulk actions with per-order authorization, timeline, print invoice/packing slip, partial fulfillment, backorders/preorders with promised dates, and customer notifications.

Support cash, COD, bank transfer, manual mobile-payment verification, split payments, deposits, customer credit, and gateway adapters for Bangladesh payment services such as bKash, Nagad, and SSLCommerz where current official merchant APIs and credentials allow. Manual reference entry is not a verified online payment.

Live adapters require server-side verification, signed callbacks when supported, unique transaction references, idempotent webhooks, retries, and reconciliation. Do not accept a browser redirect as proof of payment. Keep sandbox/demo adapters usable without live keys, with a visible demo label and deterministic success/failure/refund cases.

Provide courier adapters for configurable providers such as Pathao, Steadfast, and RedX after checking official integration availability. Include booking, labels where supported, tracking, COD collection/remittance, delivery failure, return-to-origin, and reconciliation. Record unsupported live operations honestly and supply a functional local simulator.

Returns/exchanges must support line-level quantities, inspection, sellable/damaged disposition, approval, refund method, exchange price difference, and original-sale linkage. Enforce returnable quantities and refund ceilings; handle partial returns and discounts consistently. Do not restock until the defined physical receipt/inspection event. Preserve financial and inventory history.

## 8. POS and branch operations

Build a keyboard-friendly, touch-friendly POS with barcode scanner input, search, variant selector, stock display, customer selection, price tiers, authorized discounts, hold/resume carts, payment splits, sales on credit, receipt printing, refund/exchange, and cashier shift opening/closing.

Include cash drawer opening balance, cash in/out with reasons, expected versus counted cash, discrepancy approval, daily close, and branch/cashier reporting. Support thermal receipts and Bengali A4 invoices. Define hardware/browser limitations honestly. Do not claim offline selling unless conflict-safe synchronization is implemented and tested; baseline POS may require connectivity.

A POS sale must update payments, inventory, accounting, customer ledger, and receipt atomically or through a reliable, documented eventual-consistency mechanism. Duplicate submissions must not create duplicate sales.

## 9. Wholesale, tailoring, and manufacturing

Wholesale: dealer/customer price groups, quantity breaks, quotations, quote-to-order conversion, sales orders, deposits, credit limits, due dates, statements, partial dispatches, and collections.

Tailoring: customer measurement profiles with restricted access, measurement history, custom design/reference images, fabric supplied by customer or business, customization charges, work orders, fitting dates, assignments, promised delivery, deposits, balances, alteration/rework, and customer handover.

Manufacturing: raw materials/fabric/trims, unit conversions, bill of materials, size/color production plans, work batches, cutting/sewing/embroidery/finishing stages, subcontractors/job work, material issue/return, wastage, work in progress, quality inspection, finished goods receipt, and production costing. Prevent consumption or finished stock from being posted twice. Include a tested production workflow from fabric purchase to sale of finished Panjabi.

## 10. Accounting and business reports

Implement a coherent double-entry accounting core with configurable chart of accounts, balanced journal entries, fiscal periods, posting rules, receivables/payables, cash/bank accounts, expenses, owner contributions/drawings, supplier/customer ledgers, and reversals instead of deleting posted transactions.

Define posting rules for sales, discounts, taxes, inventory cost/COGS, purchases, returns, refunds, customer advances, COD clearing/remittance, transfers, payroll, and production. Inter-branch transfers within the same business must not inflate revenue. Use a documented inventory valuation method such as moving weighted average and explain production cost allocation. Never infer profit solely from revenue minus cash payments.

Reports: sales by branch/channel/product/variant/staff; gross profit; stock valuation/aging/low stock; purchases; customer/supplier dues; pending and canceled orders; returns; courier/COD reconciliation; cash shifts; tax summary; trial balance; profit and loss; balance sheet; cash movement; production costs; attendance/payroll; consolidated versus branch views.

Make tax/VAT settings configurable. Use current authoritative guidance before implementing jurisdiction-specific rules; do not invent statutory rates or claim legal compliance. Protect reports/exports with permissions and tenant/branch filters. Show reporting period and explain metrics.

## 11. Customers, marketing, and communication

Provide customer profiles, purchase history, notes, tags, segments, due balances, loyalty points, store credit, coupons, gift vouchers, consent/preferences, and optional referral campaigns. Voucher/points balances require transactional ledgers, expiry, reversals, and abuse controls.

Support transactional email and configurable SMS adapters, notification templates in Bengali/English, delivery logs, retries, and customer preferences. Marketing messages require appropriate consent. Social order entry and shareable product links must work without pretending to integrate private social accounts. Abandoned-cart campaigns should be optional and consent-aware.

## 12. Staff, attendance, and payroll

Provide employees, branch assignments, shifts, leave requests/approvals, attendance, overtime policies, salary components, deductions, advances, payroll runs, payslips, and payroll approval/posting.

Integrate `laravel-attendance-hub` only through verified supported device protocols/providers. Do not promise support for every machine. Provide a device capability matrix, secure credential storage, employee/device mappings, connection diagnostics, ingestion logs, duplicate-event protection, timezone handling, and manual/CSV attendance fallback. Retain raw events for traceable corrections and handle overnight shifts and offline device synchronization.

## 13. AI and catalog import

Use `laravel-ai-hub` behind application services for optional product descriptions, Bengali/English translations, SEO suggestions, campaign drafts, and authorized analytics assistance. Use `laravel-ai-catalog` for supported image/URL/file catalog drafts. Provide per-tenant configuration, encrypted credentials, quotas, usage/cost visibility, timeouts, retries, and mock providers.

All imported/generated catalog data follows draft → review → approve before publishing or changing prices/stock. Preserve source provenance and confidence where available. AI cannot autonomously issue refunds, change stock, post accounting entries, or access other tenants.

URL imports must prevent SSRF: block private/loopback/link-local and metadata targets, validate DNS and redirects, restrict protocols/ports, enforce size/time limits, and sanitize extracted content. Treat external page content as untrusted input, including prompt injection. Import only authorized public content; respect access controls, applicable terms, and content/image permissions. Never bypass login, paywalls, or anti-bot controls.

The entire core business application must remain usable with AI disabled and with no paid external services.

## 14. Security and operational quality

Implement secure authentication, password resets, optional two-factor authentication, email verification where appropriate, session controls, rate limiting, CSRF/XSS protections, validated uploads, secret encryption, policies, and least-privilege permissions. Never use public storage for private documents or unrestricted device/provider credentials.

Use database transactions, appropriate locks, unique constraints, idempotency keys, and durable outbox/after-commit dispatch for external side effects. Queue jobs must carry explicit tenant context and revalidate permissions where applicable. Webhook endpoints must establish tenant identity through trusted provider mappings.

Add indexes, pagination, eager loading, queue-backed imports/reports, caching with tenant-aware keys, image optimization, and meaningful empty/loading/error states. Include health checks, structured logs, queue failure handling, backup/restore documentation, scheduler/worker configuration, and safe migration/deployment instructions.

Provide accessible focus states, form labels, error messages, mobile layouts, and sufficient contrast. Every visible action must work or clearly explain a real configuration requirement. Do not ship dead buttons, fake KPI values, placeholder policy text presented as final, or undocumented TODO workflows.

## 15. Realistic demo data and users

Provide idempotent demo seeders and a documented demo installation command. Use fictional data, licensed/local product imagery with attribution where needed, and no real customer information. Demo credentials are for local/demo environments only; production must not silently seed them.

Seed two isolated businesses: a multi-branch retailer/manufacturer and an independent online-only seller. Include Dhaka, Chattogram, and Sylhet branches where applicable, warehouses, at least 30 varied products and 80 size/color variants, raw materials, suppliers, wholesale customers, retail customers, employees, and realistic historical transactions.

Include storefront/POS/wholesale orders in different states; split and COD payments; partial receipts; transfers in transit; low stock; returns/exchanges; tailoring work; production batches; expenses; balanced accounting entries; and payroll/attendance examples. Ensure seeded totals and ledgers reconcile. Provide a clean empty-tenant onboarding example as well.

Create and document demo accounts for platform admin, business owner, branch manager, cashier, inventory manager, accountant, production supervisor, HR officer, and customer, plus an owner for the second tenant. Use a clear shared demo password such as `DemoOnly!2026`, hash it normally, and gate accounts/visible demo access to demo environments. Scope each account appropriately and provide a role/branch credentials table in `docs/DEMO.md`.

Offer guided demo journeys on a public demo page. Shared public demos must block credential replacement, destructive tenant-wide operations, real outbound messaging, and live financial integrations. Provide an explicit demo reset command guarded against production execution.

## 16. Required tests and acceptance evidence

Use meaningful automated feature/integration tests against actual services and database behavior. Include browser tests for critical user journeys. Use the production database engine for locking/concurrency checks where SQLite cannot verify the behavior.

Required acceptance scenarios:

1. New owner onboards, selects a preset, creates a branch, imports products, and completes a sale.
2. Guest selects a Panjabi variant, checks out, receives a secure confirmation, and tracks delivery; customer account can view only its own orders.
3. Staff verifies a pending order, reserves stock, packs it, books demo courier delivery, and reconciles COD remittance.
4. Cashier opens a shift, scans products, takes split payment, prints a receipt, processes a partial exchange/refund, and closes with reconciled cash.
5. Two concurrent buyers attempt the final unit; inventory rules prevent unauthorized overselling.
6. Duplicate checkout, payment callbacks, courier events, and device events produce no duplicate business effects.
7. Supplier PO is partially received and paid; stock and supplier balances reconcile.
8. A branch transfer is dispatched and partially received with a discrepancy; quantities remain conserved.
9. A customer returns one item; refund, stock disposition, COGS reversal, and customer balance agree.
10. A wholesale quote becomes a credit order; credit limits, partial dispatch, and later collection work.
11. A tailoring job records measurements/deposit, progresses through work, and is handed over with balance paid.
12. A production batch consumes materials, records wastage/cost, and receives finished variants exactly once.
13. Attendance events produce an approved payroll run and readable Bengali payslips using a mock device.
14. Disabling a module blocks new work through both UI and direct requests while safely reconciling existing operations.
15. Cross-tenant and unauthorized cross-branch reads/writes/exports/files/jobs are rejected, including tampered IDs and guessed URLs.
16. AI import creates only a review draft; SSRF and prompt-injection inputs cannot access internal resources or trigger business mutations.
17. Journal postings balance, trial balance reconciles, and seeded reports agree with underlying ledgers.
18. Bengali storefront, Unicode slugs, Bengali PDF shaping, thermal receipt, mobile checkout, and keyboard POS are visually inspected.
19. Fresh install and demo setup work from documented commands; app works with all external providers disabled.
20. Backup/restore procedure is exercised in a nonproduction environment and results recorded.

Record actual commands, test outcomes, screenshots where useful, and remaining environment limitations in `docs/VERIFICATION.md`. Do not claim physical-device or live payment tests when only simulators ran.

## 17. Implementation sequence and delivery

Deliver complete vertical slices in this order while maintaining working software:

1. Compatibility audit, architecture, tenant/branch security, authentication, module registry, onboarding, and demo foundation.
2. Catalog, inventory ledger, purchasing, branch transfers, and permissions.
3. Storefront, landing pages, checkout, order state machines, payments, fulfillment, and returns.
4. POS, shifts, wholesale, customers, loyalty, and accounting reconciliation.
5. Tailoring, manufacturing, HR, attendance, payroll, and reports.
6. AI/import adapters, notification/courier integrations, optional SaaS billing, and operational hardening.
7. End-to-end tests, visual verification, clean-install validation, documentation, and final feature audit.

Optional SaaS subscriptions, when enabled, include plans, module entitlements, trials, renewal/cancellation, invoices, payment adapters, grace periods, and documented read-only access on expiry. Keep merchant product-sale payments separate from platform subscription payments. No live billing without configured credentials.

Required deliverables:

- Complete application source, migrations, factories, seeders, tests, assets, lockfiles, and `.env.example` with no secrets.
- This product scope preserved in documentation; an application README containing exact installation/run/build/test commands, queue/scheduler setup, URLs, demo accounts, supported versions, and deployment instructions.
- `docs/ARCHITECTURE.md`, `docs/MODULES.md`, `docs/PACKAGES.md`, `docs/DEMO.md`, `docs/WORKFLOWS.md`, `docs/INTEGRATIONS.md`, `docs/DEPLOYMENT.md`, `docs/VERIFICATION.md`, and `docs/IMPLEMENTATION_STATUS.md`.
- Schema/relationship overview, module dependency matrix, state transition rules, stock and accounting posting rules, role/permission matrix, integration capability matrix, and backup/restore instructions.
- Public feature pages and README feature matrix that distinguish implemented, demo/simulated, externally configured, optional, and blocked capabilities. Marketing copy must match the working application.

Before finishing, run relevant tests and static checks, build frontend assets, migrate and seed a fresh database, exercise the principal demo journeys, and audit every requirement against the implementation checklist. Fix failures and broken navigation. Summarize what works, exact local access details, test evidence, and any remaining live-service configuration. If an external dependency is unavailable, complete the working local alternative and state the precise limitation. Never describe unfinished work as complete.
