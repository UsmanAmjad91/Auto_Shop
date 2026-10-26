# AutoShop Manager

**Vehicle inventory, auto repair & business management system** built with Laravel 13, MySQL, Bootstrap 5, AJAX and a REST API.

One dashboard runs a dealership and repair shop end to end: vehicles (with camera VIN scanning), parts inventory, repair / interior / wash & detailing jobs, employees, attendance, payroll, customers, suppliers, invoices, payments, lender financing, reports and system administration. All modules share data: issuing parts to a job moves stock and adds to the vehicle's cost, a sale invoice marks the vehicle sold, and payments update invoice and vehicle balances.

> Every record shown in screenshots and demos is created by the **optional** `DemoDataSeeder` and labelled `[DEMO]`. Production installs start empty.

---

## Contents
1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation — Linux](#installation--linux-apache-or-nginx)
4. [Installation — Windows (XAMPP / Laragon)](#installation--windows-xampp-or-laragon)
5. [First login & initial setup](#first-login--initial-setup)
6. [Queues, scheduler, mail](#queues-scheduler--mail)
7. [Testing](#testing)
8. [What needs external credentials / HTTPS / hardware](#what-needs-external-credentials-paid-services-https-or-hardware)
9. [Documentation index](#documentation-index)

---

## Features

| Area | Highlights |
|---|---|
| **Accounts & security** | Registration with **admin approval** (pending → approved / rejected / suspended / disabled), email verification, optional SMS phone verification, optional or required **2FA** (authenticator app, email code or SMS code + recovery codes), remember-me, password reset, login history, active-session management, login rate limiting, audit log |
| **Roles & permissions** | 11 default roles, unlimited custom roles, ~100 module/action permissions (view, create, edit, delete, approve, export, print, manage payments, view financials, manage settings…) enforced in middleware, policies and record-level scopes |
| **Dashboard** | 16 live KPI cards, 7 Chart.js charts, alerts, pending approvals, activity timeline, quick actions, AJAX auto-refresh — every widget filtered by permission |
| **Vehicles** | AJAX list (table or photo-card view, 15+ filters, sorting, column chooser, saved presets, URL-synced filters, bulk actions, CSV/Excel/PDF/print export), full add/edit form, **camera VIN scanner** (barcode + OCR) with **NHTSA vPIC** decoding, photos, private documents, inspections, status history, expenses, QR stock labels, printable/PDF info sheet, CSV import, custom fields |
| **Vehicle financials** | Purchase, parts, labor, interior, detailing and other costs are derived from their sources (no double counting) → total investment, asking/sale price, actual or estimated profit, amount paid, balance, payment status |
| **Parts inventory** | Parts with OEM/SKU/barcode, compatibility, multi-location stock, reserve/unreserve, add/remove/adjust, transfer, damaged, return to supplier, assign to vehicle — all **atomic, row-locked transactions** with an immutable movement ledger; negative stock blocked unless authorised; low-stock alerts; purchase orders with partial receiving and supplier payments; CSV import |
| **Service jobs** | Repair Jobs, Interior Services and Washing & Detailing share one engine: services/labor/parts/material lines (parts deducted from stock), multiple employees with roles/hours, supervisor, checklists, before/progress/after photos, job card print, status workflow that updates the vehicle's repair & inventory status, one-click invoicing |
| **Employees & payroll** | Profiles, documents, pay-rate **history**, work schedules, self-service clock in/out & breaks, manager corrections (audited), payroll periods generated from attendance with configurable overtime rules, bonuses/deductions/advances, approval lock, partial payments, overdue tracking, PDF payslips |
| **Billing** | Vehicle sale / repair / parts / interior / detailing / combined invoices, discounts, taxes, automatic numbering, branded PDF (3 templates), print, email with PDF, deposits, partial payments, multi-invoice allocation, refunds, reversals, receipts, statements, overdue reminders, **lender financing** kept as receivable until settlement |
| **CRM** | Customers (vehicles owned, purchases, repairs, invoices, payments, outstanding balance, attachments, statements) and suppliers (parts, POs, purchases, paid/pending) |
| **Reports** | 17 reports incl. inventory value, vehicle P&L, sales, repair jobs/revenue, parts purchases/consumption/valuation, low stock, attendance, payroll, receivables (with aging & lender split), payables, daily/weekly/monthly expenses, sales by employee, revenue by service type, monthly business performance — export to CSV / Excel / PDF / print |
| **Settings center** | Company, logo/favicon, timezone, date format, currency, taxes, numbering, theme (light/dark/auto, colours, font, layout, live preview), SMTP + test, SMS provider + test, verification toggles, API integrations (encrypted keys), repair statuses, lookups, custom fields, backups, data retention, maintenance mode, integration logs |
| **Platform** | Global search (Ctrl+K), notification centre, Quick Add, REST API v1 (Sanctum), scheduled jobs (reminders, overdue refresh, backups, pruning), responsive mobile layout |

---

## Requirements

* PHP **8.3+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` (optional), `fileinfo`, `gd`, `zip`, `intl`
* **MySQL 8+** or **MariaDB 10.6+** (SQLite works for automated tests)
* Composer 2, Node.js 20+ and npm
* `mysqldump` on the server for automated backups (optional)

---

## Installation — Linux (Apache or Nginx)

```bash
# 1. Get the code
git clone https://github.com/UsmanAmjad91/Auto_Shop.git autoshop && cd autoshop

# 2. PHP dependencies
composer install --no-dev --optimize-autoloader     # omit --no-dev for development/tests

# 3. Environment
cp .env.example .env
php artisan key:generate
#   edit .env: APP_URL, DB_*, MAIL_*  (see comments in the file)

# 4. Database (create it first)
mysql -u root -p -e "CREATE DATABASE autoshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'autoshop'@'localhost' IDENTIFIED BY 'change-me';
  GRANT ALL ON autoshop.* TO 'autoshop'@'localhost';"

php artisan migrate --seed          # tables + roles, permissions, statuses, lookups (no users)
php artisan storage:link            # public photos (/storage)

# 5. Create the first Super Administrator (password typed hidden, or generated)
php artisan autoshop:create-admin

# 6. Frontend assets
npm ci && npm run build

# 7. Run (development)
php artisan serve                   # http://127.0.0.1:8000
php artisan queue:work              # second terminal: emails/notifications
```

Optional demo data (never in production): `php artisan db:seed --class=DemoDataSeeder`

For Nginx/Apache virtual hosts, HTTPS, Supervisor and cron see **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

## Installation — Windows (XAMPP or Laragon)

**Laragon (recommended):** it ships PHP 8.3, MySQL, Composer, Node and auto virtual hosts.
1. Put the project in `C:\laragon\www\autoshop` (or `git clone` there).
2. Open the Laragon terminal in that folder and run steps 2–6 above (`copy .env.example .env` instead of `cp`).
3. In Laragon *Menu → MySQL → Create database* `autoshop`; set `DB_USERNAME=root`, `DB_PASSWORD=` (empty by default) in `.env`.
4. Reload Laragon — the site is served at `http://autoshop.test`. Set `APP_URL=http://autoshop.test`.

**XAMPP:**
1. Use XAMPP with **PHP 8.3+**; enable `extension=gd`, `zip`, `intl` in `php.ini`.
2. Install Composer and Node.js separately.
3. Clone into `C:\xampp\htdocs\autoshop`, run steps 2–6 above in a terminal (`copy .env.example .env`).
4. Create the database in phpMyAdmin (`utf8mb4_unicode_ci`).
5. Point Apache at the `public` folder, e.g. in `httpd-vhosts.conf`:
   ```apache
   <VirtualHost *:80>
       ServerName autoshop.local
       DocumentRoot "C:/xampp/htdocs/autoshop/public"
       <Directory "C:/xampp/htdocs/autoshop/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
   and add `127.0.0.1 autoshop.local` to `C:\Windows\System32\drivers\etc\hosts`. (`mod_rewrite` must be enabled.)
6. Queue worker: keep `php artisan queue:work` running in a terminal (or set `QUEUE_CONNECTION=sync` for small installs).
7. Scheduler: create a Windows Task Scheduler task running every minute: `C:\xampp\php\php.exe C:\xampp\htdocs\autoshop\artisan schedule:run`.

## First login & initial setup

1. Sign in with the account created by `autoshop:create-admin` (there is **no** default password).
2. **System Settings → General**: company name, logo, address, currency, tax rate, numbering prefixes.
3. **Email**: SMTP server (or keep `.env`), then *Send test email*.
4. **Verification**: choose admin approval, email/SMS verification and 2FA policy.
5. **Lookups**: departments, warehouses, part categories and service prices.
6. **Roles & Permissions**: review the default roles or create your own.
7. Approve new registrations under **Users** (pending accounts appear on the dashboard).

## Queues, scheduler & mail

* Emails and notifications are queued (`QUEUE_CONNECTION=database`). Run `php artisan queue:work --tries=3` (Supervisor in production). OTP codes are sent synchronously.
* Add **one** cron entry: `* * * * * cd /path/to/autoshop && php artisan schedule:run >> /dev/null 2>&1`. It runs:
  * `autoshop:refresh-statuses` (00:30) — overdue invoices / payroll
  * `autoshop:payment-reminders` (09:00) — customer reminders per settings
  * `autoshop:backup --scheduled` (02:00) — gzipped mysqldump when enabled
  * `autoshop:prune` (weekly) — data-retention policy
* Mail: set `MAIL_*` in `.env` or *Settings → Email*. With `MAIL_MAILER=log` messages are written to `storage/logs/laravel.log` (nothing is delivered).

## Testing

```bash
composer install && npm ci && npm run build
php artisan test                                   # SQLite in-memory (default)
DB_CONNECTION=mysql DB_DATABASE=autoshop_test php artisan test   # against MySQL
./vendor/bin/pint --test                           # code style
```
67 feature/unit tests (≈500 assertions) cover authentication & approval, 2FA, RBAC (page, action and record level, API), vehicles & VIN decoding, inventory atomicity, purchase orders, service jobs & cost roll-up, invoices/payments/refunds/reversals/financing, payroll & attendance, reports, exports and a smoke test that renders every page. CI config: `.github/workflows/tests.yml`.

## What needs external credentials, paid services, HTTPS or hardware

| Feature | Requirement |
|---|---|
| Camera VIN scanning | **HTTPS** (or `localhost`), a device camera and browser permission. Text OCR downloads the Tesseract engine (~10 MB) from the jsDelivr CDN on first use. Manual entry always works. |
| VIN decoding | NHTSA vPIC is free and keyless but needs outbound internet. Alternative providers need their own API key/URL (Settings → Integrations). Nothing is fabricated if a lookup fails. |
| Email (verification, reset, invoices, reminders) | A working SMTP / mail provider and a running queue worker. |
| SMS (phone verification, SMS 2FA) | A paid provider account (Twilio, Vonage or an HTTP gateway). SMS is **not free**; email codes are the low-cost alternative. The default `log` provider sends nothing. |
| Card payments | Process cards on your terminal/gateway; this system records payments only and **never stores card numbers**. Gateway keys can be stored (encrypted) for future integration. |
| Automated backups | `mysqldump` on the server, the scheduler cron and enough disk space. |
| Payroll | Calculates from attendance and your configured rules; statutory tax withholding is **not** computed — enter it as deductions / confirm with an accountant. |

## Documentation index

* [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — system architecture, data-flow rules, **database relationship design (ER diagram)**, folder structure, implementation phases
* [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — production deployment (Nginx/Apache, HTTPS, Supervisor, cron, hardening, backup & restore, upgrades)
* [docs/API.md](docs/API.md) — REST API v1 reference
* [docs/REQUIREMENTS_CHECKLIST.md](docs/REQUIREMENTS_CHECKLIST.md) — every requirement of the specification mapped to its implementation

## License

Proprietary — © the repository owner. Third-party packages retain their own licenses.
