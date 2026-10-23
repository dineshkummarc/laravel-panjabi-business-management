# Integrations

## Core operation without credentials

Storefront COD checkout, manual verified payment recording, POS, purchasing, transfers, tailoring, basic manufacturing/payroll, reports, and PDF invoices work without paid services.

## Simulation versus live operation

| Integration | Local behavior | Live status |
| --- | --- | --- |
| Payment gateway | `DemoGateway`: attempt, HMAC verification, success/failure, amount and replay checks | No bKash/Nagad/SSLCommerz adapter |
| Courier | `Courier`: booking, delivery, COD clearing, fee-aware remittance | No real booking/tracking provider |
| Attendance device | Attendance Hub fake driver, typed punches, deduplication | No configured physical device |
| AI catalog | AI Hub fake in demo; Catalog pipeline/review | Optional operator-configured provider; not live-tested |
| Email | Laravel mail infrastructure/password reset | Configure SMTP/provider; transactional business notifications not implemented |
| SMS/social APIs | None | Planned |

## Demo callback

`POST /integrations/demo/payment-callback` is CSRF-exempt because it represents a provider callback. It requires demo mode and `X-Demo-Signature`, the HMAC-SHA256 of the exact raw body using the application key. It looks up the trusted attempt before establishing tenant context and checks the amount. It never trusts a submitted organization ID.

Do not treat this local contract as a real merchant integration. A live adapter must use the provider's official signing/verification scheme, merchant mapping, reconciliation and refund rules.

## AI settings

The application requires `AI_LIVE_ENABLED=true` outside demo mode before calling an external provider. Set the provider/model/key using the installed AI Hub and AI Catalog configuration. Review current provider model support rather than relying on package marketing model names. Credentials are operator-level in this build; per-tenant encrypted-key UI and full cost-budget controls are not implemented.

The app's live URL reader accepts public HTTPS hostnames on port 443, rejects authentication fragments/private targets, checks DNS results, pins a validated IPv4 address, validates redirect destinations, and caps download size/time. Its production behavior still needs broader hostile-network integration tests. Demo mode does not fetch submitted URLs.

## Disabled package surfaces

Keep these disabled unless the corresponding isolation and authorization is intentionally implemented:

```dotenv
AI_CATALOG_API=false
AI_CATALOG_STUDIO=false
AI_HUB_UI=false
AI_HUB_FILAMENT=false
ATTENDANCE_ADMS_ENABLED=false
ATTENDANCE_FILAMENT_ENABLED=false
DRUTOGOTI_ENABLED=false
```

Hardware support is protocol/driver dependent. Attendance Hub includes drivers for families such as ZKTeco, ADMS, Hikvision, Suprema, Dahua and bridges; this repository does not claim tested connectivity to any of them.
