# Deploying to Namecheap shared hosting (cPanel)

This guide uses `CPUSER` for your cPanel username and `yourdomain.com` for your domain. Replace both everywhere.

Final layout on the server:
```
/home/CPUSER/autoshop/            ← the Laravel project (NOT web-accessible)
/home/CPUSER/autoshop/public/     ← the only web-visible folder (domain points here)
```
Never put the whole project inside `public_html`: `.env` (database password, app key) would become downloadable.

---

## 1. Prepare the files on your PC
Shared hosting has no Node.js, so build the frontend locally:
```bat
cd E:\wamp64\www\...\Auto_Shop
git pull origin feature/autoshop-manager
npm ci
npm run build
```
This creates `public/build`, which is **required** on the server.

Then make a ZIP of the project folder **without** `node_modules`, `.env`, `storage/logs/*` and `storage/framework/sessions/*`.
* With SSH (step 3) you can leave out `vendor` too; Composer installs it on the server.
* Without SSH, run `composer install --no-dev --optimize-autoloader` locally first and **include** `vendor`.

## 2. cPanel settings
Log in at **Namecheap → Hosting List → Go to cPanel**.

1. **Select PHP Version** (or *MultiPHP Manager*): set **PHP 8.3** for the domain. Under *Extensions* tick `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`, `intl`, `bcmath`, `curl`, `openssl`, `tokenizer`, `xml`, `ctype`.
   In *Options* set `upload_max_filesize = 20M`, `post_max_size = 25M`, `memory_limit = 256M`.
2. **MySQL® Databases**:
   * Create database `autoshop` → it becomes `CPUSER_autoshop`.
   * Create user `autoshop` with a strong password → `CPUSER_autoshop`.
   * *Add User To Database* → tick **ALL PRIVILEGES**.
3. **SSL/TLS Status**: run AutoSSL for `yourdomain.com` (free HTTPS). HTTPS is needed for the camera VIN scanner and secure logins.
4. **Email Accounts**: create e.g. `noreply@yourdomain.com` (used to send verification, invoices and reminders).

## 3. Upload the project
**Option A — File Manager (easiest):**
1. *File Manager* → open your home folder `/home/CPUSER` (one level **above** `public_html`).
2. Create folder `autoshop`, open it, **Upload** the ZIP, then right-click → **Extract**.
3. Make sure `artisan`, `app/`, `public/` sit directly inside `/home/CPUSER/autoshop`, not in a nested subfolder.

**Option B — Git (cPanel → Git™ Version Control):** clone `https://github.com/UsmanAmjad91/Auto_Shop.git` into `/home/CPUSER/autoshop` on branch `feature/autoshop-manager`. A private repo needs a deploy key. You still have to upload `public/build` from step 1.

## 4. Point the domain at `autoshop/public`
**Addon domain or subdomain** (e.g. `app.yourdomain.com`): cPanel → *Domains* → **Create A New Domain** (or *Manage* the existing one), untick *Share document root*, and set the document root to `autoshop/public`.

**Main domain** (`public_html` cannot be re-pointed): use SSH (step 5) to replace `public_html` with a link:
```bash
mv ~/public_html ~/public_html_old          # keep a backup of anything that was there
ln -s ~/autoshop/public ~/public_html
```
No SSH? As a fallback, copy the **contents** of `autoshop/public` into `public_html`, then edit `public_html/index.php` and change the two paths
`__DIR__.'/../vendor/autoload.php'` → `__DIR__.'/../autoshop/vendor/autoload.php'` and
`__DIR__.'/../bootstrap/app.php'` → `__DIR__.'/../autoshop/bootstrap/app.php'`.
Repeat this copy after every update.

## 5. Configure and install (SSH)
Enable SSH: cPanel → **SSH Access** → *Manage SSH Keys* (or ask Namecheap support to enable shell access). Namecheap uses **port 21098**:
```bash
ssh -p 21098 CPUSER@yourdomain.com
cd ~/autoshop
cp .env.example .env
nano .env
```
Set these values in `.env`:
```
APP_NAME="AutoShop Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPUSER_autoshop
DB_USERNAME=CPUSER_autoshop
DB_PASSWORD=your-db-password
DB_ENGINE=InnoDB

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
QUEUE_VIA_SCHEDULER=true
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-email-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

LOG_LEVEL=warning
```
(Using Namecheap *Private Email* instead of cPanel email? Use `MAIL_HOST=mail.privateemail.com`.)

Then run:
```bash
php -v                                       # must show 8.3 — if not, use /opt/alt/php83/usr/bin/php instead of php
composer install --no-dev --optimize-autoloader   # skip if you uploaded vendor/
php artisan key:generate
php artisan migrate --force --seed           # tables + roles/permissions (no users, no demo data)
php artisan storage:link
php artisan autoshop:create-admin            # your Super Administrator login
php artisan optimize
chmod -R 775 storage bootstrap/cache
```
If `composer` is not found, run `php ~/composer.phar …` after downloading it with `curl -sS https://getcomposer.org/installer | php`.

**No SSH at all?** Import the schema from your PC instead: run `php artisan migrate --seed` locally against an empty database, export it with phpMyAdmin, then import it in cPanel → phpMyAdmin. Create the admin locally the same way. Set `APP_KEY` in the server `.env` to the value from your local `.env`.

## 6. Cron job (scheduler + email queue)
cPanel → **Cron Jobs** → *Common Settings: Once Per Minute* → Command:
```
/usr/local/bin/php /home/CPUSER/autoshop/artisan schedule:run >> /dev/null 2>&1
```
If PHP 8.3 is not the default CLI, use `/opt/alt/php83/usr/bin/php` instead (check with `which php` or `ls /opt/alt/`).
With `QUEUE_VIA_SCHEDULER=true`, this single cron also sends queued emails and notifications. It also runs overdue checks, payment reminders, backups and data pruning.

## 7. First login & checks
1. Open `https://yourdomain.com`, then log in with the admin you created.
2. **System Settings → General**: company name, logo, currency, tax.
3. **Settings → Email → Send test email** to confirm SMTP works.
4. **Settings → Backups → Back up now**. If it fails because `mysqldump`/`proc_open` is blocked on your plan, use cPanel → **Backup** (download a database backup) or JetBackup instead.
5. Force HTTPS: cPanel → *Domains* → toggle **Force HTTPS Redirect**.

## 8. Updating later
1. On your PC: `git pull`, `npm run build`, then ZIP and upload the changed files (or `git pull` on the server).
2. Upload the new `public/build` folder.
3. Then over SSH:
```bash
cd ~/autoshop
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan autoshop:sync-permissions
php artisan optimize
```

## Troubleshooting
| Problem | Fix |
|---|---|
| 500 error / blank page | Check `storage/logs/laravel.log`. Run `chmod -R 775 storage bootstrap/cache`. Temporarily set `APP_DEBUG=true` (switch back afterwards). |
| Page loads without styling | `public/build` is missing. Run `npm run build` locally and upload it. |
| 404 on every page except home | The document root is not `autoshop/public`, or `public/.htaccess` was not uploaded (enable *Show Hidden Files* in File Manager). |
| Photos/logo do not show | `php artisan storage:link` was not run, or the link was broken by a copy. Delete `public/storage` and run it again. |
| `1071 Specified key was too long` | Make sure `DB_ENGINE=InnoDB`, then `php artisan migrate:fresh --seed --force` (only on an empty install!). |
| `could not find driver` | Enable `pdo_mysql` in *Select PHP Version → Extensions*. |
| Emails not arriving | Run the SMTP test in Settings. Check the cron job is running. Look at `jobs` / `failed_jobs` in phpMyAdmin. |
| Changes to `.env` ignored | Run `php artisan optimize:clear && php artisan optimize`. |
