# Architecture Reference

## Contents

1. Entry points
2. Application layers
3. Domain ownership
4. Authentication and authorization
5. Data and deployment
6. Source discovery commands

## Entry points

| Concern | Source |
|---|---|
| Framework bootstrap and middleware aliases | `bootstrap/app.php` |
| Admin web routes | `routes/admin.php` |
| Public/admin API and webhooks | `routes/api.php` |
| Miscellaneous web routes and payment mock | `routes/web.php` |
| Scheduler | `routes/console.php` |
| OpenAPI contract | `public/docs/openapi.json` |
| Interactive API docs | `/api/docs` |

Admin URLs are locale-prefixed: `/{locale}/admin`. Public storefront APIs use `/api/public`.

## Application layers

| Layer | Location | Responsibility |
|---|---|---|
| Middleware | `app/Http/Middleware` | Auth state, feature, locale, security headers |
| Form requests | `app/Http/Requests` | Normalization and validation |
| Admin controllers | `app/Http/Controllers/Admin` | Admin orchestration and Blade responses |
| API controllers | `app/Http/Controllers/Api` | JSON orchestration |
| Auth controllers | `app/Http/Controllers/Auth` | Session/reset flows |
| Resources | `app/Http/Resources` | Public field allow-list |
| Services | `app/Services` | Domain rules and integrations |
| Support | `app/Support` | Cross-cutting policy/helpers |
| Models | `app/Models` | Persistence and relations |
| Migrations | `database/migrations` | Schema history |
| Seeders | `database/seeders` | Foundation and disposable demo data |
| Views | `resources/views/admin` | Bootstrap admin UI |
| Tests | `tests/Feature`, `tests/Unit` | Executable contracts |

## Domain ownership

### Catalog

- Product/category/brand rules: `app/Services/Catalog`.
- Variant option matrix and resolution: `ProductOptionService`, `ProductVariantResolver`.
- Localized slugs: `LocalizedSlugService`, `HasLocalizedSlugs`.
- Import/export: `ProductImportService`, admin product controller and feature tests.

### Commerce

- Promotion calculation/reservation: `PromotionService`.
- Voucher validation: `Voucher` model, checkout orchestration, voucher tests.
- Checkout: `Api/PublicController::checkout`.
- State policy: `OrderStateTransitionService`.
- Stock ledger: `OrderStockService`, `InventoryMovement`.
- Payment ledger: `PaymentTransactionService`, `PaymentTransaction`.
- Refunds: admin `OrderController`, refund models, lifecycle tests.

### Integrations

- VNPAY URL/signature policy: `VNPAYService`.
- VNPAY callbacks: `Api/PublicController`.
- Shipping settings/push: `ShippingService`.
- GHTK callback: `Api/WebhookController`.
- Media: `CloudinaryService`.
- Translation: `Services/Translation`.
- Notifications: jobs, mail classes, `NotificationHelper`.

### Administration

- Feature policy: `FeatureGate`, `EnsureFeatureEnabled`.
- Roles/permissions: `User`, `Role`, `Permission`, authorization middleware, `AppServiceProvider`.
- Session/token revocation: `UserAccessService`.
- Audit: `ActivityLogger`, `AdminActivityLog`.
- Installation health: `InstallCore`, `CheckCore`, `FoundationSeeder`.

## Authentication and authorization

| Surface | Mechanism |
|---|---|
| Admin web | Session `auth` + `admin` + `can:*`/`superadmin` |
| Admin API | Sanctum + ability `admin` + active admin middleware |
| Customer API | Sanctum + ability `customer` + active customer middleware |
| Optional customer context | `$request->user('sanctum')` on selected public actions |
| GHTK webhook | Provider token + active realtime tracking + throttle |
| VNPAY | HMAC signature + configured TMN code + amount/order checks |

Authorization belongs at both route/function level and object ownership level. A protected route alone
does not prevent IDOR.

## Data and deployment

- Production database is MySQL/MariaDB; tests use SQLite in-memory.
- Sensitive settings use `EncryptedJson` or encrypted model accessors.
- Foundation installation is idempotent through `core:install`.
- `FoundationSeeder` is production-safe; `DemoSeeder` is disposable-environment only.
- Queue workers deliver order/invoice/notification jobs.
- Scheduler expires vouchers daily.
- Web document root must point at `public/`.

## Source discovery commands

```bash
rg --files app routes database tests config resources/views/admin
php artisan route:list --except-vendor
rg -n "function checkout|function refund|function handleGHTK" app tests
rg -n "middleware|can:|feature:" routes bootstrap/app.php
rg -n "Schema::create|Schema::table" database/migrations
rg -n "public function test_" tests
```
