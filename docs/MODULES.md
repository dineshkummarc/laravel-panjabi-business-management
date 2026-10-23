# Modules

The source of truth is `config/business.php`; owner configuration is `/business/modules`.

| Module | Dependencies |
| --- | --- |
| Inventory | None declared; accounting postings remain an internal service capability |
| Storefront | Inventory, orders |
| Orders | Inventory, accounting |
| POS | Orders |
| Courier | Orders |
| Purchasing | Inventory, accounting |
| Wholesale | Orders |
| Tailoring | None declared; preset includes accounting |
| Production | Inventory |
| Accounting | None |
| HR | Accounting |
| Attendance | HR |
| CRM | None |
| AI | None |

Module dependency validation rejects a selected module when its declared prerequisite is missing. Changing configuration preserves records. POS and new operational entry points enforce activation; Filament resources use their module setting for create/edit permission. Historical screens remain accessible to authorized roles.

Existing order payments, returns and status transitions can be reconciled after a module is disabled. Transfer receipts and existing courier reconciliation likewise remain possible. No live provider operation is canceled by a module toggle.

The module model is currently organization-wide. Branch overrides, a full registry of background tasks/webhooks, automated dependency selection, and a configurable drain policy are not implemented. Internal ledger helpers can still run as part of another enabled business operation even if the Accounting UI module is off; this is not a complete module-execution sandbox.

Shared demos reject module changes; newly registered non-demo workspaces can configure their selection.
