# ROWA Portal — Residential Owners Welfare Association Management Portal

A web portal for an owners' welfare association. It manages owners, houses and plots, monthly maintenance, payments, receipts (PDF and WhatsApp), expenses, reports and audit logs.

**Stack:** PHP 8.1+ · CodeIgniter 3.1.13 · MySQL 8.0.16+ or MariaDB 10.4+ · Bootstrap 5 · jQuery · DataTables · Chart.js · Font Awesome · Dompdf

It has no Composer, Node.js or build step. Every library is included in the repository.

---

## 1. Folder layout

```text
public/        ← web document root (index.php, .htaccess, assets/, uploads/branding/)
application/   ← CodeIgniter application (controllers, models, views, libraries, config)
system/        ← CodeIgniter 3.1.13 core (unmodified)
storage/       ← writable runtime files: logs, cache, receipts, imports, backups, expense bills
database/      ← SQL install scripts
docs/          ← design documents
.env           ← environment settings (never commit; see .env.example)
```

Only `public/` should be reachable from the browser.

## 2. Requirements

| Item | Requirement |
|---|---|
| PHP | 8.1 or newer, with these extensions: mysqli, mbstring, json, fileinfo, openssl, dom, xml, ctype, session |
| PHP (recommended) | zlib (compressed backups), curl (WhatsApp Cloud API), gd (only if the logo is a PNG; JPG logos work without it) |
| Database | MySQL 8.0.16+ or MariaDB 10.4+ |
| Web server | Apache 2.4 with mod_rewrite, or Nginx |

## 3. Installation

### 3.1 Database
```bash
mysql -u root -p -e "CREATE DATABASE rowa_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p --default-character-set=utf8mb4 rowa_portal < database/01_schema.sql
mysql -u root -p --default-character-set=utf8mb4 rowa_portal < database/02_seed_master.sql
# optional demo data (never on a live server):
mysql -u root -p --default-character-set=utf8mb4 rowa_portal < database/03_demo_data.sql
# optional integrity check (every issue_count must be 0):
mysql -u root -p -t rowa_portal < database/04_verify.sql
```
On a live server, create a dedicated MySQL user with privileges on `rowa_portal` only. Don't use `root`.

### 3.2 Environment file
```bash
cp .env.example .env
php -r "echo bin2hex(random_bytes(32));"     # paste into APP_ENCRYPTION_KEY
```
Then set `APP_ENV`, `APP_URL` (with a trailing slash), the `DB_*` values and the `MAIL_*` values.

### 3.3 Folder permissions (Linux)
```bash
chown -R www-data:www-data storage public/uploads
find storage public/uploads -type d -exec chmod 750 {} \;
chmod 640 .env
```

### 3.4 Web server

**Apache virtual host (recommended)**
```apache
<VirtualHost *:80>
    ServerName portal.example.com
    DocumentRoot /var/www/rowa-portal/public
    <Directory /var/www/rowa-portal/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
In `public/.htaccess`, set `RewriteBase /` when the portal is served at a domain root.

**XAMPP on Windows (sub-folder, e.g. `http://localhost/rowa/`)**: add this to `xampp/apache/conf/extra/httpd-xampp.conf` and restart Apache:
```apache
Alias /rowa "D:/TEST_DEMO/public"
<Directory "D:/TEST_DEMO/public">
    AllowOverride All
    Require all granted
</Directory>
```
`public/.htaccess` already contains `RewriteBase /rowa/`, and `.env` uses `APP_URL=http://localhost/rowa/`.

**Nginx**
```nginx
server {
    server_name portal.example.com;
    root /var/www/rowa-portal/public;
    index index.php;
    client_max_body_size 8M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
    location ~ /\. { deny all; }
    location ^~ /uploads/ { location ~ \.php$ { deny all; } }
}
```

**Shared hosting without a configurable document root:** upload everything. The root `.htaccess` forwards requests to `public/`, and `.htaccess` files deny direct access to `application/`, `system/`, `storage/`, `database/` and `docs/`.

**Quick local test without Apache:**
```bash
php -S 127.0.0.1:8080 -t public server.php
# then set APP_URL=http://127.0.0.1:8080/ in .env (or as an environment variable)
```

### 3.5 Verify
Open `<APP_URL>system-check`. It runs only when `APP_ENV=development`; in production it is available to the Super Admin. Every item should be green or a warning.

## 4. Default login
| Username | Password | Notes |
|---|---|---|
| `admin` | `Admin@123` | Super Admin. You must change the password at first login. |
| `secretary`, `treasurer`, `viewer` | `Demo@123` | Created only by the demo data |

## 5. First-time setup (in the portal)
Do these in order after the first login:

1. **Settings → Association**: check the name, registration number, address, contact details and logo. These print on every receipt and statement. The receipt prefix (default `REC`) and the due day are set here as well.
2. **Maintenance → Maintenance Rates**: the opening rate is ₹300 per plot per month for both categories, *Constructed* (Built) and *Vacant Plot* (Under Construction / Not Built). To change a rate later, add a new rate from a future month. Bills that have already been generated never change.
3. **Owners**: add owners one at a time (an owner can have several plots), or use **Owners → Import Owners** for a CSV file. Download the template from that page. The columns are Plot No, House No, Block, Street, Owner Name, Mobile, WhatsApp, Email, Owner Type and Built Status. A row with the same owner name and mobile number adds another plot to that owner. Run *Validate only* first; it shows every problem without saving anything. The limit is 2,000 rows per file.
4. **Tenants** (rented houses only): record the tenant, ID proof (last 4 digits only), emergency contact and rental agreement. Bills always go to the owner.
5. **Users**: create a login for each committee member with the right role (Admin, Treasurer or Viewer). Don't share the Super Admin login.

**Every month:**
1. **Maintenance → Generate Monthly**: preview, then generate. Each plot gets one bill per month. Generating the same month again only adds plots that are missing, so it never duplicates.
2. **Payments → Collect Payment**: search by name, plot, house number or mobile number. Partial payments and advance payments are allowed. Money is applied to the oldest unpaid bills first, and any extra is kept as advance credit for future bills. Each payment gets the next receipt number (`REC-2026-000001`, …).
3. **Receipt**: download it as a PDF (A4 or half-page), print it, or send it on WhatsApp.
4. **Maintenance → Pending Reminders**: send WhatsApp reminders to owners with unpaid bills.
5. **Expenses / Other Income**: record spending (attach the bill) and any other income.
6. **Reports**: Outstanding, Monthly Collection, Owner Statement (PDF and Excel) and Income & Expense.

A wrong payment is **cancelled**, never deleted. Cancelling keeps the receipt number (marked *Cancelled*) and puts the bills back to unpaid.

## 6. WhatsApp
- **Click-to-Chat (default, free):** the portal opens WhatsApp (web or app) with the message already typed, and the staff member presses *Send*. Receipts are sent as a message with the details. Attach the downloaded PDF by hand if you want to send it too.
- **Cloud API (automatic):** sends without anyone pressing *Send* and attaches the receipt PDF. It needs a Meta Business account, a verified phone number and approved message templates. Put the access token in `.env` (`WHATSAPP_CLOUD_ACCESS_TOKEN`), never in the database. Enter the Phone Number ID in **WhatsApp → Settings**, then use *Send test message* there. Template wording is edited in **WhatsApp → Message Templates**. For Cloud API, the approved Meta template name and language go on the same page.
- Every message, whether prepared or sent, is listed in **WhatsApp → Message Log**.

## 7. Backups and restore
- **Settings → Database Backup → Create backup now** saves a compressed `.sql.gz` file in `storage/backups/` (not reachable from the web). If `MYSQLDUMP_PATH` in `.env` points to `mysqldump`, it is used; otherwise a built-in PHP exporter is used. Both produce a complete, restorable file.
- Download a backup at least monthly and keep it off the server.
- On a Linux server, also schedule a nightly dump with cron:
  ```bash
  # /etc/cron.d/rowa-backup   (credentials in /root/.my.cnf, not on the command line)
  30 1 * * * root mysqldump --single-transaction --routines rowa_portal | gzip > /var/backups/rowa/rowa_$(date +\%F).sql.gz && find /var/backups/rowa -name '*.sql.gz' -mtime +60 -delete
  ```
- **Restore:** unzip the file, then import it into an empty database: `mysql -u USER -p rowa_portal < backup.sql`.

## 8. Going-live checklist
| # | Item |
|---|---|
| 1 | HTTPS certificate installed; `.env`: `APP_ENV=production`, `APP_FORCE_HTTPS=true`, `APP_URL=https://…/` |
| 2 | New `APP_ENCRYPTION_KEY` generated on the server (never reuse the development key) |
| 3 | Dedicated MySQL user with rights on `rowa_portal` only; strong password in `.env` |
| 4 | Only `01_schema.sql` and `02_seed_master.sql` loaded; **no demo data** |
| 5 | Logged in as `admin`, changed the password, then checked Settings → Association and the logo |
| 6 | Document root is `public/` (or the root `.htaccess` is in place); `RewriteBase` in `public/.htaccess` matches the URL path |
| 7 | `storage/` and `public/uploads/` writable by the web server; `.env` readable by the web server only |
| 8 | PHP `display_errors=Off`; `expose_php=Off` |
| 9 | `MAIL_*` set and *Forgot password* tested (or `MAIL_PROTOCOL=log` if not needed) |
| 10 | `/system-check` (as Super Admin) shows no errors |
| 11 | First backup created and downloaded; nightly cron backup scheduled |
| 12 | `WHATSAPP_CLOUD_BASE_URL` left blank |

## 9. Troubleshooting
| Symptom | Fix |
|---|---|
| Every page except the login shows *404 Not Found* | `mod_rewrite` is off, or `RewriteBase` in `public/.htaccess` doesn't match the URL path |
| Links point to the wrong address | `APP_URL` in `.env` must be the full URL of `public/`, ending with `/` |
| "The action you have requested is not allowed" | The form was open longer than 2 hours (security token expired). Reload the page and submit again |
| Logo missing from PDF receipts | Use a JPG logo, or enable the PHP `gd` extension for PNG logos |
| Locked out after wrong passwords | Wait 15 minutes, or another admin can unlock the user in **Users** |
| Forgot-password e-mail not received | Check the `MAIL_*` settings; with `MAIL_PROTOCOL=log` the e-mail is written to `storage/logs/` |
| Something else | Check `storage/logs/log-YYYY-MM-DD.log` and `/system-check` |

## 10. PHP 8.2+ note
CodeIgniter 3.1.13 creates dynamic properties inside `system/`, which PHP 8.2 and newer report as `E_DEPRECATED`. These are notices, not errors. `public/index.php` hides `E_DEPRECATED` only; every other notice, warning and error is still reported. Application classes are marked `#[\AllowDynamicProperties]`.
