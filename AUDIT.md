# Audit controls

This document describes how the RUMA IT Support system records activity, how
the record is protected, and how to check it. It is written for the compliance
officer, auditors and the IT Manager.

## What is recorded

There are two complementary logs.

### 1. Activity log (`audit_logs`)

One entry per significant action, written by the application:

| Area | Recorded actions |
|---|---|
| Sign-in | `auth.login`, `auth.logout`, `auth.login_failed`, `auth.login_locked`, `auth.account_locked`, `auth.login_throttled` |
| Account | `account.password_changed`, `account.password_change_failed`, `account.notification_preferences` |
| Tickets | `ticket.created`, `ticket.assigned`, `ticket.reassigned`, `ticket.released`, `ticket.status_changed`, `ticket.priority_changed`, `ticket.comment`, `ticket.internal_note`, `ticket.attachment_uploaded`, `ticket.attachment_viewed`, `ticket.sla_warning`, `ticket.sla_breached`, `ticket.auto_closed` |
| Users & roles | `user.created`, `user.updated`, `user.role_changed`, `user.password_reset`, `user.unlocked` |
| Settings | `settings.category_*`, `settings.department_*`, `settings.sla_updated` |
| Security | `access.denied` (any refused page or action), `security.csrf_failed` |
| The audit log itself | `audit.viewed`, `audit.exported` (with filters and row count), `audit.integrity_check` (with result), `audit.anchor` (daily fingerprint) |
| Reports | `report.viewed` and `report.exported` (Phase 5) |

Each entry stores: who (user ID and email at the time), what (action, entity,
description, structured details), where (IP address, browser) and when (server
clock, microsecond precision). The time is set by the database, so the
application cannot back-date an entry.

### 2. Data change log (`db_change_logs`)

Database triggers record **every insert, update and delete** on the business
tables: users, roles, permissions, departments, categories, priorities, SLA
policies, settings, tickets, comments, attachments, ticket status history and
notification preferences. Each entry holds the table, record ID, operation, the
full **old values** and **new values** as JSON, and the user, IP and browser.

**Why triggers instead of application code:** triggers also capture changes made
*outside* the application, such as an edit in phpMyAdmin or the MySQL command
line. Those entries have no application user and are shown as **"Outside the
app"**, labelled with the database account (for example `db:cpuser@localhost`).
For an inspector, this is the most important property of the log.

Not captured, by design:

- **Password hashes.** These appear only as `[redacted]` or `[redacted: changed]`.
- **High-volume operational tables:** sign-in attempts (already in the activity
  log), notifications and the email queue.

## How the log is protected

| Control | What it stops | Where |
|---|---|---|
| No edit or delete screens or routes | Changes through the application | `config/routes.php` (audit routes are read-only) |
| `BEFORE UPDATE` / `BEFORE DELETE` triggers that reject the statement | Edits, even by privileged database users | `database/triggers.sql` |
| Least-privilege database account (`SELECT`, `INSERT` only on audit tables) | Edits by the application account | `database/grants.sql` (VPS / dedicated MySQL) |
| SHA-256 hash chain | Undetected tampering of any kind | `database/triggers.sql`, `app/Services/HashChain.php` |
| Daily fingerprint emailed to auditors | Rewriting the whole chain | `tools/cron.php` (`audit_anchor`) |

### The hash chain

When an entry is written, the database computes
`SHA-256(previous entry's fingerprint | every field of this entry)` and stores it
with the entry. A row lock on `audit_chain_head` ensures concurrent writes form
one unbroken sequence. Consequences:

- **Changing** any field of any entry makes that entry's fingerprint wrong.
- **Deleting** an entry breaks the link to the next one.
- **Inserting** an entry in the middle breaks the link after it.
- **Removing the newest entries** leaves the chain shorter than the recorded head.

### Hosting differences (cPanel)

On cPanel shared hosting, MySQL privileges can only be set per database, not
per table, so the application account technically holds `UPDATE`, `DELETE` and
`TRUNCATE` on the audit tables. What still applies:

- `UPDATE` and `DELETE` are blocked by the triggers.
- `TRUNCATE` and `DROP TRIGGER` bypass triggers, but the integrity check detects
  the result: the chain no longer reaches the recorded head, or the fingerprints
  no longer match.
- Someone with full database access could in theory rebuild the whole chain
  consistently. The **daily fingerprint email** defends against this: the new
  chain would not match fingerprints auditors already hold in their mailboxes.

On a VPS, apply `database/grants.sql` so the application account cannot
truncate or alter the audit tables at all.

## How to check the log

1. Sign in as an Auditor or the IT Manager.
2. Open **Audit log → Integrity check** and choose **Run integrity check**.
3. The result says either **Intact**, or which entry is the first affected and
   why (changed, removed, inserted, or newest entries missing). The check itself
   is recorded.
4. Compare **Current fingerprints** with the most recent daily email. The entry
   count should be the same or higher. The fingerprint from the email must
   appear in the chain: export the activity log for that date and find the
   `audit.anchor` entry.

**If a check fails:** change nothing. Inform the IT Manager and compliance
officer, keep the database backups from before the reported entry's date, and
export both logs (CSV) for investigation.

## Searching and exporting

- **Activity:** filter by date range, person (or "System or direct database
  access"), action, entity, record ID, IP address or free text.
- **Data changes:** filter by date, person, table, type of change or record ID.
  "Show values" lists only the fields that changed, before and after.
- **Export:** CSV (no row limit, streamed) or PDF (up to 2,000 rows, branded,
  with filters, generation time and the person who generated it). CSV exports
  include each entry's fingerprint. Cells starting with `=`, `+`, `-` or `@`
  are prefixed with `'` so spreadsheets do not run them as formulas.

## Who can do what

| Permission | Auditor | IT Manager |
|---|:-:|:-:|
| View activity and data changes (`audit.view`) | ✓ | ✓ |
| Run the integrity check (`audit.verify`) | ✓ | ✓ |
| Export (`audit.export`) | ✓ | |
| Edit or delete entries | nobody | nobody |

The IT Manager can view but not export, which separates duties: the people
running the service are not the ones who take copies of its audit trail.
Permissions can be changed in the `role_permissions` table; such a change is
itself recorded in the data change log.

## Retention

Audit tables are never pruned by the application. Plan database backups to keep
at least the period your regulator requires, typically 6 to 10 years for
healthcare IT records. Confirm the period with your compliance officer.
