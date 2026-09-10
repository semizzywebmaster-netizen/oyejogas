# cPanel deployment guide (Oyejo Gas)

Upload the release file `oyejo-gas.zip` to any standard cPanel shared
host (PHP 8.1+, MySQL/MariaDB, Apache + mod_rewrite). No root access,
no Composer and no Node build step are required on the server.

## 1. Upload and extract

1. cPanel → **File Manager** → open `public_html/` (or a subfolder /
   subdomain folder for a test site).
2. **Upload** `oyejo-gas.zip`, then right-click → **Extract** into the
   document root so `index.php` sits directly in it.
3. Delete the uploaded `oyejo-gas.zip` after extracting (it must not
   stay web-accessible).
4. Permissions: files `644`, folders `755`. Make `storage/` (and
   everything under it) writable by PHP — if the installer complains,
   set `storage/` and `.env` (once created) to `775`/`664`, or fix
   ownership via your host's PHP user; never use `777`.

## 2. Create the database

1. cPanel → **MySQL Database Wizard**: create database (e.g.
   `cpuser_oyejo`), user (e.g. `cpuser_oyejo`) and a strong password,
   grant **ALL PRIVILEGES**. Note the full `cpuser_` prefixed names.
2. No need to import SQL — the installer creates all tables and seeds.
   (Advanced: pre-create is enough; the installer never needs the
   MySQL root account.)

## 3. Run the web installer

1. Visit `https://your-domain/install/` (use HTTPS — see §5).
2. Requirements screen: PHP version, `pdo_mysql`, `mbstring`, `curl`,
   `openssl`, writable `.env`/`storage/` are checked with fix hints.
3. Enter DB host (usually `localhost`), name, user, password, and the
   admin account details. The installer writes `.env`, imports
   `database/schema.sql` + `database/seeds.sql`, creates the admin,
   and locks itself (`storage/install.lock`).
4. Re-running `/install/` afterwards is blocked by the lock file.
   To reinstall, delete `.env` and `storage/install.lock` first
   (this wipes configuration only, not the database).

## 4. Cron jobs (cPanel → Cron Jobs, once per line)

Use PHP-CLI (not `wget` on URLs — `cron/` is blocked over HTTP):

    * * * * * /usr/bin/php /home/cpuser/public_html/cron/send-notifications.php >> /dev/null 2>&1
    0 8 * * * /usr/bin/php /home/cpuser/public_html/cron/pickup-reminders.php >> /dev/null 2>&1
    0 2 * * * /usr/bin/php /home/cpuser/public_html/cron/backup.php >> /dev/null 2>&1
    0 3 * * * /usr/bin/php /home/cpuser/public_html/cron/rotate-logs.php >> /dev/null 2>&1

Replace `/home/cpuser/public_html` with your real document root and
confirm the PHP binary path with your host (`/usr/bin/php`,
`/usr/local/bin/php` or a versioned path such as
`/opt/cpanel/ea-php84/root/usr/bin/php`).

## 5. SSL, domain and e-mail

- cPanel → **SSL/TLS Status** → enable AutoSSL so the whole shop runs
  on `https://` (required for PWA install + push notifications).
- **Site URL**: set the real URL in Admin → Settings → Site so links
  in e-mails/receipts are correct.
- **E-mail**: fill SMTP settings in Admin → Settings → Notifications
  (or `.env` `MAIL_*`). Test with a password-reset e-mail to yourself.
- **WhatsApp/SMS gateways** (optional): paste provider credentials in
  Admin → Settings → Notifications or `.env`; never commit them.

## 6. Security checklist after deploy

- `.env`, `storage/`, `cron/`, `database/`, `install/`(locked),
  `addons/` and `*.sql` are denied by `.htaccess` — verify by opening
  `https://your-domain/.env` (must be 403/404, never contents).
- Admin URL is `/admin/`; rename nothing — lockout + RBAC apply.
  Create individual staff accounts (Admin → Staff) instead of sharing
  the owner login.
- Keep daily backups ON (Admin → Backups) and download one off-server
  copy weekly (see `06-BACKUP-RESTORE-GUIDE.md`).
- Apply host PHP upgrades on the 8.x line; re-run the installer
  requirements page after major moves (copy the folder, unlock, run).

## 7. Updating to a new release

1. Take a backup (Admin → Backups → Run now) and download it.
2. Upload the new release to a staging subfolder, copy `.env` and
   `storage/` over, test, then swap folders (or overwrite in place
   outside business hours).
3. Add-ons with pending migrations show an **Update** badge in
   Admin → Add-ons — run updates there, never by hand-editing tables.
