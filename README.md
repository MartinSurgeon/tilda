# RUMA Hospital: IT Support & Maintenance Reporting System

Internal ticketing for **RUMA Hospital (ruma.hospital)**. Staff report hardware
and software problems, the IT team resolves them, management receives monthly
reports, and every action is recorded in a tamper-evident audit trail.

> **Status: Phase 2 (Tickets) complete.** Sign-in, roles, user management,
> ticket submission, queue, workflow, SLAs, attachments and settings work.
> Next: Phase 3 (live dashboards, notification centre, email).

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
mysql -u root ruma_itsm < database/seed.sql

# 3. Configuration
cp .env.example .env      # then set APP_ENV=local, APP_DEBUG=true, DB_* values

# 4. Run
php -S 127.0.0.1:8000 -t public public/index.php
```

Open <http://127.0.0.1:8000>.

**CSS:** `public/assets/css/app.css` is committed. Rebuild only after changing
classes in views: `npm install` once, then `npm run build` (or `npm run watch`).

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
3. Create the database and user in *MySQL Databases*, then import the three SQL
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
