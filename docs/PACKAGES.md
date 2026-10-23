# Requested package audit

Selected application: PHP 8.3+, Laravel 12, Filament 5. Exact installed versions and revisions are in `composer.lock`.

Laravel 13 was initially evaluated, but Drutogoti, Laravel Admin Panel, and Attendance Hub metadata declared Laravel support through 12. The application was resolved on Laravel 12 instead of forcing incompatible package constraints.

| Package | Version | Locked source revision |
| --- | --- | --- |
| `imrandevbd/drutogoti` | `dev-main` | `6c5b4c94bf42` |
| `imrandevbd/laravel-admin-panel` | `dev-main` | `67305195926b` |
| `imrandevbd/laravel-ai-catalog` | `dev-main` | `07b5783f3ec0` |
| `imrandevbd/laravel-ai-hub` | `v1.6.0` | `5fc1bf2e13b6` |
| `imrandevbd/laravel-attendance-hub` | `dev-main` | `7bd5a7b4a9b2` |
| `imrandevbd/laravel-unicode-pdf` | `dev-main` | `bc0288d34227` |
| `imrandevbd/laravel-universal-slug` | `dev-main` | `9fdcf1659a51` |

AI Hub has a stable release. The other requested packages expose development branches in the metadata used for this build. The lockfile makes the current installation reproducible; compatibility must be rechecked before updating.

## Integration choices

- **Drutogoti:** installed; watchers and dashboard disabled. No claim of automatic performance improvement. Production telemetry could include cross-tenant operational information, so do not expose a default shared dashboard.
- **Laravel Admin Panel:** separate protected `/tools/admin-panel` route. Owner-only middleware is added. An application view replaces the package sample dashboard with actual scoped metrics. Filament remains the primary admin interface.
- **AI Catalog:** called behind application services with explicit tenant identifiers and schema/category handling. Public package API and studio routes are disabled. Application approval creates an unpublished product without using an unrestricted package publishing endpoint.
- **AI Hub:** powers the catalog package. Fake provider in demo; live operation requires explicit enablement. Package settings UI and Filament plugin are disabled.
- **Attendance Hub:** its fake driver generates typed punch DTOs for normalized local records. ADMS ingress and package Filament resources are disabled. Real hardware is not tested.
- **Unicode PDF:** native layout/writer wrapped in `LocalPdfEngine` so PDF requests use bundled fonts rather than downloading them. Bengali and Latin fonts/licenses are included.
- **Universal Slug:** documented `universal_slug()` helper is used by demo catalog generation and AI approval. Business-scoped database uniqueness remains authoritative.

The captured metadata is preserved in `package-metadata.json`. Sources: the linked Packagist pages in the main README, installed package source/README files, and Composer's resolver. Package claims about model availability, universal hardware support, or compliance are not adopted as application guarantees.
