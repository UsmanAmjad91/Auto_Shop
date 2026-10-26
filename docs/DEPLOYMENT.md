# Production deployment

## 1. Server checklist
* Ubuntu 22.04/24.04 (or any Laravel-compatible host), PHP 8.3-FPM with `pdo_mysql mbstring xml gd zip intl curl fileinfo`
* MySQL 8 / MariaDB 10.6+, Nginx or Apache, Node 20+ (build step only), Composer 2, Supervisor, `mysqldump`
* A domain with **HTTPS** (required for camera VIN scanning and secure cookies) — e.g. Let's Encrypt `certbot`

## 2. Deploy
```bash
cd /var/www && git clone https://github.com/UsmanAmjad91/Auto_Shop.git autoshop && cd autoshop
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```
Edit `.env`:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://autoshop.example.com
DB_DATABASE=autoshop  DB_USERNAME=autoshop  DB_PASSWORD=<strong password>
SESSION_SECURE_COOKIE=true
SESSION_DRIVER=database
QUEUE_CONNECTION=database
MAIL_MAILER=smtp  MAIL_HOST=…  MAIL_PORT=587  MAIL_USERNAME=…  MAIL_PASSWORD=…  MAIL_FROM_ADDRESS=…
LOG_LEVEL=warning
```
```bash
php artisan migrate --force --seed        # reference data only — no users, no demo data
php artisan storage:link
php artisan autoshop:create-admin          # first Super Administrator (no default password)
php artisan optimize                       # config, route, event & view cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```
Never run `DemoDataSeeder` in production (it refuses to run when `APP_ENV=production`).

## 3. Web server
**Nginx**
```nginx
server {
    listen 443 ssl http2;
    server_name autoshop.example.com;
    root /var/www/autoshop/public;
    index index.php;
    client_max_body_size 25M;                       # photo/document uploads
    ssl_certificate     /etc/letsencrypt/live/autoshop.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/autoshop.example.com/privkey.pem;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name autoshop.example.com; return 301 https://$host$request_uri; }
```
**Apache**: `DocumentRoot /var/www/autoshop/public`, `AllowOverride All`, `a2enmod rewrite ssl headers`. The shipped `public/.htaccess` handles routing.

Set `upload_max_filesize = 20M` and `post_max_size = 25M` in `php.ini`.

## 4. Queue worker (Supervisor)
`/etc/supervisor/conf.d/autoshop-worker.conf`
```ini
[program:autoshop-worker]
command=php /var/www/autoshop/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/www/autoshop/storage/logs/worker.log
```
`supervisorctl reread && supervisorctl update && supervisorctl start autoshop-worker:*`

## 5. Scheduler
`crontab -u www-data -e`
```
* * * * * cd /var/www/autoshop && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Security hardening
* `APP_DEBUG=false`, HTTPS only, `SESSION_SECURE_COOKIE=true`
* Keep `.env` outside version control and readable only by the web user; rotate `APP_KEY` only with a re-encryption plan (encrypted settings / 2FA secrets depend on it)
* Settings → Verification: enable admin approval, email verification and 2FA (`required` for staff with financial access)
* Settings → Security: session timeout, login attempts, minimum password length
* Restrict MySQL user to the app database; regular OS and package updates (`composer audit`, `npm audit`)
* Uploaded documents/attachments live on the private disk (`storage/app/private`) and are streamed only after authorisation; photos use random file names on the public disk

## 7. Backup & restore
* Enable *Settings → Operations → Scheduled database backups* (daily 02:00 or weekly), or run `php artisan autoshop:backup`
* Files are written to `storage/app/private/backups/*.sql.gz`; copy them off-server (e.g. `rclone`, S3) together with `storage/app` (uploads)
* **Restore**
  1. Enable maintenance mode (Settings → Security, or `php artisan down`)
  2. `gunzip < backup-YYYY-MM-DD_HHMMSS.sql.gz | mysql -u autoshop -p autoshop`
  3. Restore `storage/app` if needed, then `php artisan migrate --force && php artisan optimize:clear && php artisan optimize`
  4. Disable maintenance mode
* Test restores periodically on a staging server.

## 8. Upgrades
```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan autoshop:sync-permissions     # adds new permissions/roles (existing role edits are kept)
php artisan optimize
php artisan queue:restart
php artisan up
```
