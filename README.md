# RUMA Hospital: IT Support & Maintenance Reporting System

Internal ticketing for **RUMA Hospital (ruma.hospital)**. Staff report hardware
and software problems, the IT team resolves them, management receives monthly
reports, and every action is recorded in a tamper-evident audit trail.

> **Status: complete (all 6 build phases).** Ticketing, live dashboards, notifications,
> email, SLA tracking, audit trail with integrity checks, monthly reports and exports.
> See [SECURITY.md](SECURITY.md) and [AUDIT.md](AUDIT.md) for the controls, and
> [Deployment](#deployment) to go live.

- **Stack:** PHP 8.1+ (no framework), MySQL 8 / MariaDB 10.4+, Tailwind CSS (pre-built), vanilla JS
- **Runs on:** cPanel shared hosting or any VPS. Node is only needed on a developer machine.

---

## Quick start (local, XAMPP / Windows)

```bash
# 1. PHP dependencies (Composer is kept in tools/)
php tools/composer.phar install

# 2. Database
mysql -u root -e "CREATE DATABASE ruma_itsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root ruma_itsm < database/schema.sql
mysql -u root ruma_itsm < database/triggers.sql
mysql -u root ruma_itsm < database/change_triggers.sql
mysql -u root ruma_itsm < database/seed.sql

# 3. Configuration
cp .env.example .env      # then set APP_ENV=local, APP_DEBUG=true, DB_* values

# 4. Run
php -S 127.0.0.1:8000 -t public public/index.php
```

Open <http://127.0.0.1:8000>.

**CSS:** `public/assets/css/app.css` is committed. Rebuild only after changing
classes in views: `npm install` once, then `npm run build` (or `npm run watch`).

## Demo data

To review dashboards and reports with realistic history (about 180 tickets over 75 days,
plus 8 demo staff accounts):

```bash
php tools/seed-demo-tickets.php
```

It refuses to run when `APP_ENV=production`. Everything it creates is recorded in the audit
trail as `demo-seed`, so use a fresh database for go-live.

## Reports

*Reports* (IT Manager, Management) shows the monthly maintenance report: plain-language summary,
key figures compared with the previous month, daily trend, breakdowns by priority, category
and department, top recurring issues, repeat locations, technician workload and today's open
backlog. Filter by month or custom dates (up to a year), department, category, priority,
current status or technician. *Download PDF* gives a branded A4 report; *CSV* gives every section
in one sheet. Viewing and exporting are recorded in the audit log.

## Demo accounts

All share the password **`Ruma@2026!`** and must choose a new one at first sign-in.

| Email | Role | Can |
|---|---|---|
| `admin@ruma.hospital` | IT Manager / Admin | Everything except audit export |
| `tech@ruma.hospital`, `tech2@ruma.hospital` | IT Technician | Work the queue, assign tickets |
| `employee@ruma.hospital` | Employee | Raise and follow own tickets |
| `management@ruma.hospital` | Management | Dashboards, reports, exports |
| `auditor@ruma.hospital` | Auditor | Audit log: view, export, verify |

Deactivate or delete these accounts before go-live.

## Roles & permissions

Permissions live in the `role_permissions` table and are checked **on the
server for every request** (`App\Core\Gate`). Routes declare the permission
they need in `config/routes.php`. Hidden buttons are cosmetic only.

| Permission | Employee | Technician | IT Manager | Management | Auditor |
|---|:-:|:-:|:-:|:-:|:-:|
| ticket.create / view_own | ✓ | ✓ | ✓ | ✓ | |
| ticket.view_all / work / assign | | ✓ | ✓ | | |
| ticket.reassign | | | ✓ | | |
| dashboard.overview, report.view / export | | | ✓ | ✓ | |
| audit.view / verify | | | ✓ | | ✓ |
| audit.export | | | | | ✓ |
| admin.users / admin.settings | | | ✓ | | |

## Ticket workflow

```
Open → Assigned → In progress ⇄ On hold → Resolved → Closed
                                          ↘ Reopened → back into work
```

- **Technicians** take an unassigned ticket (or a manager assigns it), then start, pause, resolve.
  They can only change tickets that are theirs or unowned; IT Managers can change any.
- **Requesters** confirm the fix (closes it) or reopen with a reason. They can cancel an open request.
- **SLA**: response and resolution targets per priority (*Settings → Priorities & SLA*).
  Time *On hold* does not count. Changing priority recalculates the targets.
- **Attachments**: JPG, PNG, WebP or PDF, up to 3 per message and `UPLOAD_MAX_MB` each.
  Images are re-encoded (removes location data from phone photos). Files live in `storage/uploads`
  and are only served after a permission check. Make sure PHP `upload_max_filesize` and
  `post_max_size` are at least `UPLOAD_MAX_MB` × 3.

## Live updates

Pages poll `/api/poll` every 15 seconds (*settings* `poll.interval_seconds`) and refresh only
the parts that changed, so dashboards, queues and open tickets update without a reload.
Polling was chosen over Server-Sent Events because SSE keeps one PHP process busy per open
tab, which shared hosting quickly runs out of. Polling pauses in background tabs and does
not count as activity for the idle sign-out. A region the user is typing in is never replaced.

**Notification sound:** a soft chime plays when a new notification arrives (a brighter chime, twice,
for Critical tickets and SLA warnings). It is on by default for IT staff and off for everyone else;
each person can change it, and test both chimes, under *My account → Sound*. Sounds are generated
in the browser (no audio files) and are deliberately unlike medical-device alarms. With sound on,
polling continues every 30 seconds in background tabs. Browsers only allow sound after the person
has clicked or typed on the page at least once.

## Background jobs (cron)

Run `tools/cron.php` every 5 minutes. In cPanel → *Cron Jobs*:

```
*/5 * * * * /usr/local/bin/php /home/CPANELUSER/ruma/tools/cron.php >> /home/CPANELUSER/ruma/storage/logs/cron.log 2>&1
```

It sends queued emails (3 attempts), warns when a ticket passes its SLA warning point,
escalates missed SLAs to IT Managers, closes resolved tickets after `tickets.auto_close_days`
(default 5) without a reply, and prunes old sign-in attempts. Safe to run by hand: `php tools/cron.php`.

**Email:** set `MAIL_ENABLED=true` and the `MAIL_*` SMTP values in `.env` (cPanel → *Email Accounts*
→ *Connect Devices* shows them). With `MAIL_ENABLED=false`, emails go to `storage/logs/mail.log`.
Staff choose which events they get by email under *My account*.

## Project layout

```
app/Core         Router, Request/Response, DB (PDO), Session, Csrf, Auth, Gate, Validator, View
app/Controllers  HTTP controllers (Admin/ for administration)
app/Models       Ticket, Lookup (queries)
app/Services     TicketService, TicketPolicy, Sla, Uploads, Notifier, AuditLogger, HashChain, …
config/          app.php (reads .env), routes.php
database/        schema.sql, triggers.sql, seed.sql, grants.sql, MIGRATIONS.md
public/          index.php (single entry point) + assets: the ONLY web-served folder
resources/css    Tailwind source + design tokens
views/           layouts, partials, components, pages
storage/         uploads, logs, sessions (never web-served)
```

## Deployment

### Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (with WebP), `openssl`, `dom`; `exif` recommended
- MySQL 8.0.16+ or MariaDB 10.4+, with permission to create triggers
- HTTPS
- `upload_max_filesize` ≥ `UPLOAD_MAX_MB`, `post_max_size` ≥ 3 × `UPLOAD_MAX_MB`

### Build the release (on your computer)

```bash
php tools/composer.phar install --no-dev --optimize-autoloader
npm install && npm run build        # only if views or CSS changed
```

Upload everything **except** `node_modules/`, `.git/`, `.env` and the contents of `storage/`
(keep the empty `storage/*` folders and `storage/.htaccess`).

### cPanel (shared hosting)

1. **Files:** in *File Manager*, upload the project to a folder **outside** `public_html`,
   for example `/home/CPANELUSER/ruma`.
2. **Document root:** in *Domains*, create the site (for example `it.ruma.hospital`) and set its
   document root to `/home/CPANELUSER/ruma/public`.
   *If the document root cannot be changed:* copy the contents of `public/` into `public_html`,
   then edit `public_html/index.php` so its `require` line reads
   `require '/home/CPANELUSER/ruma/app/bootstrap.php';`. Never upload `app/`, `config/`,
   `storage/`, `vendor/` or `.env` into `public_html`.
3. **PHP:** in *MultiPHP Manager* choose PHP 8.1 or newer. In *MultiPHP INI Editor* set
   `upload_max_filesize = 8M` and `post_max_size = 24M`. In *Select PHP Version → Extensions*,
   tick the extensions listed above.
4. **Database:** in *MySQL Databases*, create a database and a user with a strong password, and
   add the user with **All Privileges** (cPanel cannot grant less per table; see AUDIT.md).
5. **Import:** in *phpMyAdmin*, select the database and *Import* in this order:
   `schema.sql`, `triggers.sql`, `change_triggers.sql`, `seed.sql`.
   If `triggers.sql` fails with error 1419, ask the host to allow triggers for your account.
6. **Configure:** copy `.env.example` to `.env` in `/home/CPANELUSER/ruma` and set:
   `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://it.ruma.hospital`,
   `APP_TIMEZONE` (for example `Africa/Accra`), `SESSION_SECURE=true`, the `DB_*` values
   (cPanel prefixes names, e.g. `cpuser_ruma`), and the `MAIL_*` values from
   *Email Accounts → Connect Devices*. Set `MAIL_ENABLED=true`.
7. **HTTPS:** check *SSL/TLS Status* shows AutoSSL for the domain.
8. **Cron:** in *Cron Jobs*, add (every 5 minutes):
   ```
   */5 * * * * /usr/local/bin/php /home/CPANELUSER/ruma/tools/cron.php >> /home/CPANELUSER/ruma/storage/logs/cron.log 2>&1
   ```
9. **Check:** in *Terminal* (or via a one-off cron job), run `php /home/CPANELUSER/ruma/tools/check.php`.
10. **Go live:** follow the checklist below.

### VPS (Ubuntu, Apache or Nginx)

```bash
sudo apt install php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-gd php8.2-xml php8.2-curl php8.2-exif mariadb-server
sudo mkdir -p /var/www/ruma && sudo chown -R $USER /var/www/ruma     # upload the release here
sudo chown -R www-data:www-data /var/www/ruma/storage && sudo chmod -R 750 /var/www/ruma/storage
sudo mysql < /var/www/ruma/database/grants.sql                      # edit the passwords first
mysql -u ruma_owner -p ruma_itsm < database/schema.sql              # then triggers, change_triggers, seed
```

On a VPS, put the **`ruma_app`** account (SELECT/INSERT only on audit tables) in `.env`, and use
`ruma_owner` only for imports. Nginx site:

```nginx
server {
    server_name it.ruma.hospital;
    root /var/www/ruma/public;
    index index.php;
    client_max_body_size 24m;
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
    location ~ /\. { deny all; }
    location ~* \.(css|js|svg|png|woff2)$ { expires 1y; add_header Cache-Control "public, immutable"; }
}
```

With Apache, set `DocumentRoot /var/www/ruma/public` and `AllowOverride All` (the included
`.htaccess` files handle routing). Then add HTTPS with `certbot`, and the cron line from above
for the `www-data` user.

### Go-live checklist

- [ ] `php tools/check.php` reports **0 failures**
- [ ] Signed in as `admin@ruma.hospital`, created a personal IT Manager account, then deactivated
      every demo account (it fails the check while any is active)
- [ ] Departments, categories and SLA targets reviewed under *Settings*
- [ ] Test email received (report a test ticket, then wait for cron)
- [ ] First daily audit fingerprint email received by the auditor
- [ ] Database backups scheduled (cPanel *Backup*, or `mysqldump --single-transaction --routines --triggers` daily) and stored off the server
- [ ] Staff told the address and the "no patient data" rule

### Upgrading

1. Back up the database and the `storage/uploads` folder.
2. Upload the new release over the old one (keep `.env` and `storage/`).
3. Apply any new files in `database/migrations/` (see `database/MIGRATIONS.md`). If the schema
   changed, re-import `change_triggers.sql`.
4. Run `php tools/check.php`.

## Troubleshooting

**XAMPP: `ERROR 1114 The table 'db' is full` / `'tables_priv' is full` when running `grants.sql`.**
This is a known XAMPP MariaDB issue: those system tables were copied from
another build and are marked corrupt. It is not related to this project. To fix
it (back up first if other projects depend on custom grants):

```sql
REPAIR TABLE mysql.db, mysql.tables_priv;
```

Locally the app can simply connect as `root`. Production hosts are unaffected.
