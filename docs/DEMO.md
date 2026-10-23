# Demo guide

Set `APP_ENV=local` and `DEMO_MODE=true`, migrate, then run:

```bash
php artisan business:demo
```

The command refuses production or disabled demo mode. Seeding is idempotent at business level: existing seeded businesses are retained, not reset. The supplemental demo setup ensures the platform account/campaign exist and restores the full demo business module selection. It is not a general data-repair or reset tool.

## Accounts

Password for each fictional account: `DemoOnly!2026`.

| Email | Role / scope |
| --- | --- |
| platform@panjabi.demo | Platform overview |
| owner@panjabi.demo | Oitijjho business owner |
| manager@panjabi.demo | Dhaka manager |
| cashier@panjabi.demo | Dhaka cashier |
| inventory@panjabi.demo | Inventory/catalog/purchasing |
| accountant@panjabi.demo | Accounting/reports |
| production@panjabi.demo | Production/tailoring |
| hr@panjabi.demo | People/payroll/attendance |
| customer@panjabi.demo | Own customer history |
| seller@panjabi.demo | Separate Noor Online owner |

Visit `/demo`, `/shop/aarong-demo`, `/shop/noor-demo`, `/business`, `/admin`, or `/platform`. The `aarong-demo` slug is legacy seed naming for the fictional Oitijjho business, with no retailer affiliation.

## Data

Oitijjho contains Dhaka, Chattogram, and Sylhet branches; 30 finished designs with four sizes each; a raw cotton material variant; initial stock and journal entries; 20 sample sales; a partial purchase receipt/payment; an in-transit transfer with partial receipt; tailoring jobs and deposits; completed/planned production batches; employees, attendance, payroll, and rent expense. Noor Online has a separate smaller catalog and transactions.

## Suggested walkthrough

1. Browse a product and place a guest COD order. Save the private tracking link.
2. Sign in as owner and find the pending order. Confirm, process, and pack it.
3. Open Courier & COD, book a simulated shipment, simulate delivery, and reconcile remittance.
4. View the order payment history and trial balance.
5. Sign in as cashier, open a cash shift, sell a variant, and close against counted cash.
6. Inspect the partial purchase and transfer. Receive only a portion of the outstanding quantity.
7. Create a tailoring job; collect all funds before handover.
8. Generate an AI catalog draft in simulation mode. Review and approve it; it remains unpublished.

## Shared-demo protections

Module changes, staff creation, business settings changes, and platform suspension are blocked in demo mode. Operational fictional transactions remain writable. There is no automated expiry/reset, no complete anti-abuse platform, and no real merchant messaging. Keep real customer information and live credentials out of publicly shared demo environments.
