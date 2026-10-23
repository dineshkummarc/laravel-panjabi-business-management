# Workflows and posting rules

## Orders

```text
pending -> confirmed | canceled
confirmed -> processing | canceled
processing -> packed
packed -> shipped
shipped -> delivered | failed_delivery
failed_delivery -> shipped
delivered -> completed
```

Completion requires paid status. POS orders are created completed/delivered inside a transaction that also verifies and records full retail payment. Wholesale can remain pending with a balance. Customer requests do not automatically cancel orders or issue refunds.

Cancellation is restricted to allowed stages and unpaid orders. Paid/delivered items use the return workflow. Partial fulfillment and return-to-origin stock processing are not implemented.

## Stock

| Operation | Available stock |
| --- | --- |
| Opening/positive adjustment | Increase |
| Negative adjustment | Decrease, sufficient quantity required |
| Order accepted | Decrease |
| Unpaid order canceled | Restore original quantity |
| Sellable received return | Increase returned quantity |
| Damaged received return | No sellable increase |
| Purchase receipt | Increase received quantity |
| Transfer dispatched | Decrease source; quantity remains in transit |
| Transfer partially received | Increase destination; reduce in-transit remainder |
| Production completed | Consume configured material; receive configured finished quantity once |

## Journal patterns

| Event | Debit | Credit |
| --- | --- | --- |
| Opening stock | Inventory | Opening equity |
| Sale accepted | Receivable, COGS | Sales/delivery revenue, inventory |
| Customer payment | Cash/bank or COD clearing | Receivable |
| Purchase receipt | Inventory plus debit variance if applicable | Payable plus credit variance if applicable |
| Supplier payment | Payable | Cash/bank |
| Transfer dispatch | Inventory in transit | Source inventory |
| Transfer receipt | Destination inventory | Inventory in transit |
| Sale canceled | Sales/delivery revenue, inventory | Receivable, COGS |
| Received return | Sales returns; inventory or damage expense | Receivable/cash adjustment; COGS reversal |
| Operating expense | Operating expense | Cash/bank |
| Payroll payment | Salary expense | Cash/bank |
| Tailoring deposit/collection | Cash/bank | Customer advances |
| Tailoring handover | Customer advances | Tailoring revenue |
| Production completion | Finished inventory, debit variance if applicable | Material inventory, paid labor, credit variance if applicable |
| Simulated courier remittance | Cash/bank and courier expense | COD clearing |

Prices/costs are snapshotted on sale items. Return amounts use original item values; shipping remains charged. The first portion of a return offsets any unpaid balance, and the remainder posts as a cash refund. Actual payout must be arranged outside this application.

Stock and journal records should not be edited through ad hoc database changes. Standard costs are locked after movement; a formal revaluation flow is pending.

## Other states

- Transfer: dispatched -> partial -> received.
- Tailoring: received -> cutting -> sewing -> fitting -> ready -> delivered; handover requires full collection.
- Production: planned -> completed; completion is idempotent.
- AI draft: draft -> approved; approval creates an unpublished product, not stock or variants.
- Simulated shipment: booked -> delivered -> remitted.
- Simulated payment attempt: pending -> success or failed; verified repeated callbacks do not collect twice.
