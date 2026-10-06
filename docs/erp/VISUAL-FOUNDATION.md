# ERP visual foundation

This increment adds the navigable ERP shell pages without introducing financial writes.

## Routes

- `GET /erp` — overview, administrator and tenant-scoped condominium context, financial demo summary, cashflow period selector and shortcuts.
- `GET /erp/payables`, `/erp/receivables`, `/erp/billing`
- `GET /erp/condominiums`, `/erp/people`, `/erp/units`
- `GET /erp/bank-accounts`, `/erp/reconciliation`

Operational list screens use a shared page header, demo notice, filters, table and empty state. The cashflow selector accepts `period=7`, `period=30` or `period=month`.

## Demo data

`ErpDemoDataProvider` is read-only and returns examples in memory. Examples never become database records. Demo content is available only when `APP_ENV=development` and `ERP_DEMO_DATA_ENABLED` is true. In development, the flag defaults to enabled; set `ERP_DEMO_DATA_ENABLED=false` to hide the examples. Production always disables them, even if the flag is set to true.

Condominium names shown in examples come from the active tenant's condominium list when available. The condominium registry page always reads through `CondominiumService`; only its empty development state can show clearly marked example rows.

## Existing V1 services and access

The routes reuse the V1 tenant context, company and condominium services, administrator authorization, product access gate, session authentication and `erp.access` permission middleware. The overview rejects a selected condominium ID outside the current tenant. The legacy `feat/mvs-erp` branch was audited for reuse but not merged; this increment did not import its historical authentication/MFA, role, route or layout contracts.

Financial create, payment, billing and reconciliation operations remain unavailable until their real services and permissions are implemented. Corresponding controls are disabled or omitted.
