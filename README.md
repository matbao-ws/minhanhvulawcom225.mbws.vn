# Ecommerce Admin Core

Laravel 12 admin core for catalog, orders, inventory, payments, shipping settings, CMS, and configurable integrations. The customer storefront is intentionally not included; another project can consume the public API or provide its own frontend.

## What is included

- Data-driven roles and permissions, activity logs, and encrypted sensitive settings.
- Product and variant inventory ledger with idempotent stock deduction and restoration.
- Order state/history, partial refunds, payment transaction audit, VNPAY IPN handling, and shipping integration configuration.
- Feature configuration controlled by support. No feature or addon purchase flow is enforced in the application.
- Admin-only Blade interface at `/{locale}/admin` (default: `/vi/admin`).

## Local installation

Requirements: PHP 8.2+, Composer, and MySQL/MariaDB or SQLite. Configure the database values in `.env` before running the installer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan core:install
php artisan serve
```

`core:install` asks for the initial superadmin name, email, and a password of at least 12 characters. For CI or non-interactive deployment, provide the values explicitly:

```bash
php artisan core:install --no-interaction \
  --admin-name="Operations Admin" \
  --admin-email="admin@example.com" \
  --admin-password="use-a-strong-secret"
```

The command only runs `migrate` and the idempotent `FoundationSeeder`; it never uses `migrate:fresh` and never inserts demo catalog/order data.

## Demo data

Demo data is deliberately separate because some old demo seeders truncate their target tables. Use it only in a disposable local database:

```bash
php artisan db:seed --class=Database\\Seeders\\DemoSeeder
```

Do not run that command against a customer database.

## Production deployment

Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, correct database credentials, and mail/integration secrets through the deployment environment. Then run:

```bash
php artisan core:install --force --no-interaction \
  --admin-name="Operations Admin" \
  --admin-email="admin@example.com" \
  --admin-password="use-a-strong-secret"
php artisan core:check
php artisan optimize
```

`core:check` verifies the application key, database connection, required core schema, active administrators, and seeded foundation configuration. It performs no writes.

Order, invoice, and store notifications are queued. Run a supervised worker in production (for example, `php artisan queue:work --tries=3`) and keep `QUEUE_AFTER_COMMIT=true`. Schedule `php artisan schedule:run` every minute; it deactivates expired vouchers daily at 00:10. Point the web server document root to `public/`.

## Verification

```bash
php artisan test
php composer.phar validate --no-check-publish
```

The project keeps old package/subscription tables for backwards-compatible historic data, but they do not gate core features or addons.
