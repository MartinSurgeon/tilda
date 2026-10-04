# RUMA Hospital: IT Support & Maintenance Reporting System

Internal ticketing for **RUMA Hospital (ruma.hospital)**. Staff report hardware
and software problems, the IT team resolves them, management receives monthly
reports, and every action is recorded in a tamper-evident audit trail.

> **Status: Phase 5 (Reports) complete.** Monthly maintenance report with filters,
> comparison to the previous period, charts and branded PDF/CSV export.
> Next: Phase 6 (hardening, accessibility pass, deployment guide).

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

## Deploying to cPanel (summary)

A full guide comes in Phase 6. The essentials:

1. Upload the project **outside** `public_html` (e.g. `/home/USER/ruma`), including `vendor/`.
2. Point the domain or subdomain document root at `/home/USER/ruma/public`.
   If you cannot change it, copy `public/` into `public_html` and change the
   `require` path in `public_html/index.php` to point at `/home/USER/ruma/app/bootstrap.php`.
3. Create the database and user in *MySQL Databases*, then import the four SQL
   files in phpMyAdmin (see `database/MIGRATIONS.md`).
4. Create `.env` from `.env.example` with `APP_ENV=production`, `APP_DEBUG=false`,
   `SESSION_SECURE=true`, and make sure HTTPS is enabled (AutoSSL).
5. Make `storage/` writable by PHP (usually 755 is enough on cPanel).

## Troubleshooting

**XAMPP: `ERROR 1114 The table 'db' is full` / `'tables_priv' is full` when running `grants.sql`.**
This is a known XAMPP MariaDB issue: those system tables were copied from
another build and are marked corrupt. It is not related to this project. To fix
it (back up first if other projects depend on custom grants):

```sql
REPAIR TABLE mysql.db, mysql.tables_priv;
```

Locally the app can simply connect as `root`. Production hosts are unaffected.
