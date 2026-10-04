# Security controls

How the RUMA IT Support system protects accounts, data and the audit trail.
Written for the IT Manager and anyone reviewing the system's security. Audit
trail controls are described separately in [AUDIT.md](AUDIT.md).

## Reporting a vulnerability

Report suspected vulnerabilities privately to the IT Manager. Do not open a
ticket in the system itself (tickets are visible to the IT team). Include steps
to reproduce. Do not test against the live system without written permission.

## Summary of controls

| Threat | Control | Where |
|---|---|---|
| SQL injection | Prepared statements with native (non-emulated) prepares for every query; identifiers in SQL come only from code constants or allow-lists | `app/Core/DB.php` |
| Cross-site scripting | All output escaped with `e()` (HTML5, quotes, invalid UTF-8 substituted); strict CSP with no inline script or style; JS inserts data with `textContent` only | `app/helpers.php`, `app/Core/SecurityHeaders.php` |
| Cross-site request forgery | Per-session token checked on **every** non-GET request (form field or `X-CSRF-Token` header), compared with `hash_equals`; `SameSite=Lax` cookie; state changes are POST-only | `app/Core/Csrf.php`, `app/Core/Router.php` |
| Broken access control | Central permission check on every route (`Gate`), plus per-ticket checks (`TicketPolicy`) for viewing, commenting, internal notes, attachments and each status change; tickets you cannot see return 404 so references cannot be probed; refusals are audited | `config/routes.php`, `app/Core/Gate.php`, `app/Services/TicketPolicy.php` |
| Password guessing | 5 failures per email in 15 minutes locks the account (unknown emails lock identically, so lockout reveals nothing); 30 failures per IP blocks the IP; constant-time comparison against a dummy hash for unknown emails | `app/Core/Auth.php` |
| Weak passwords | At least 12 characters, 3 of 4 character types, not common, not containing the person's name or email, at most 72 bytes (bcrypt limit); bcrypt cost 12, rehashed automatically if the default changes | `app/Services/PasswordPolicy.php` |
| Session hijacking | New session ID at sign-in and password change; `HttpOnly`, `Secure` (on HTTPS) and `SameSite=Lax` cookie; strict mode; 30-minute idle and 12-hour absolute timeout; background polling does not extend the idle timer; sessions stored in the app's own folder, not a shared `/tmp` | `app/Core/Session.php` |
| Stolen session after a reset | Changing a password, or an admin resetting it, ends **every other** signed-in session for that account; deactivating an account ends its sessions immediately | `app/Core/Auth.php` |
| Malicious uploads | File type taken from the content (not the name or browser); only JPG, PNG, WebP, PDF; size limit; images re-encoded (strips EXIF/GPS and anything appended); PDFs must start with `%PDF-`; random file names; stored outside the web root; served only after a permission check with `nosniff`, a sandboxing CSP and `attachment` disposition for PDFs | `app/Services/Uploads.php`, `app/Controllers/AttachmentController.php` |
| Open redirects | Post-login and notification redirects accept only same-site paths (rejects `//host`, `/\host`, schemes, control characters) | `safe_path()` in `app/helpers.php` |
| Header and log injection | Control characters stripped from all input; single-line fields (titles, names, locations) collapsed to one line; email subjects never contain line breaks | `app/Core/Request.php`, `app/Services/Mailer.php` |
| Spreadsheet formula injection | CSV cells starting with `=`, `+`, `-`, `@`, tab or CR are prefixed with `'` | `app/Services/Export.php` |
| PDF generation abuse | Dompdf with remote fetching, PHP and JavaScript disabled; file access limited to `public/assets` | `app/Services/Export.php` |
| Flooding | Login rate limits (above); at most 20 new tickets per person per hour | `app/Controllers/TicketController.php` |
| Clickjacking, sniffing, leaks | `frame-ancestors 'none'` + `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy` (camera, mic, location off), COOP/CORP, HSTS on HTTPS, `X-Powered-By` removed; signed-in pages sent with `Cache-Control: no-store` | `app/Core/SecurityHeaders.php`, `app/Core/Router.php` |
| Information disclosure | Errors shown as friendly pages; details only in `storage/logs` (never shown when `APP_DEBUG=false`); login errors never say whether an email exists | `app/Core/ErrorHandler.php` |
| Exposed files | Only `public/` is web-served; `.htaccess` guards deny everything else even if the whole project is uploaded into `public_html`; `.env` is git-ignored | `.htaccess`, `public/.htaccess`, `storage/.htaccess` |
| Vulnerable dependencies | `composer audit` and `npm audit` clean at release (Dompdf 3.1, PHPMailer 6.12); no JavaScript libraries load from third-party CDNs | `composer.lock` |

## Patient data

Tickets must never contain patient information. The ticket form and every reply
box say so, and the PDF footer repeats it. The system has no field for patient
data. If patient information is entered by mistake, the IT Manager should edit
the ticket text through the database. That correction is itself recorded in the
data change log, which is the intended, auditable outcome.

## Secrets

- All secrets (database password, SMTP password) live in `.env`, outside the web
  root and outside git. `.env.example` contains no real values.
- Temporary passwords created by an administrator are shown once and never
  stored or logged in plain text.
- Password hashes never enter the audit trail (`[redacted]`).

## Operating securely

1. Serve only over HTTPS (cPanel AutoSSL is enough). Set `APP_URL=https://…`,
   `SESSION_SECURE=true`, `APP_ENV=production`, `APP_DEBUG=false`.
2. Run `php tools/check.php` after every install or upgrade. It fails if demo
   accounts or the demo password are still active in production.
3. Deactivate demo accounts. Give each person their own account; never share.
4. Review **Audit log → Activity** weekly for `auth.login_failed`,
   `access.denied` and `security.*` entries, and run the integrity check monthly.
5. Keep PHP and the server patched. Run `php tools/composer.phar audit` when
   updating dependencies.
6. Back up the database daily and keep backups off the server.

## Known limits

- **cPanel shared hosting** cannot restrict database privileges per table; see
  AUDIT.md, *Hosting differences*.
- **No multi-factor authentication.** If accounts become reachable from outside
  the hospital network, add MFA, or restrict access by IP in cPanel → *IP Blocker*
  or with an `.htaccess` allow-list.
- **Marking a notification read uses a GET link.** A malicious page could at
  most mark someone's notification as read; it cannot change any ticket data.
- **Rate limiting is per account and per IP in the database.** Large-scale
  attacks still need protection at the network edge (host firewall, Cloudflare).
