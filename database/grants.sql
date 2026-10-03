-- =============================================================================
-- Least-privilege database accounts (VPS / dedicated MySQL only).
--
-- cPanel shared hosting cannot grant per-table privileges; there the
-- append-only triggers in triggers.sql and the hash chain carry this job.
-- See AUDIT.md, section "Hosting differences".
--
-- Run as root. Replace the passwords and database name first.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS ruma_itsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Owner / migration account: used only to import schema, triggers and seed.
CREATE USER IF NOT EXISTS 'ruma_owner'@'localhost' IDENTIFIED BY 'CHANGE_ME_OWNER';
GRANT ALL PRIVILEGES ON ruma_itsm.* TO 'ruma_owner'@'localhost';

-- Application account: the only credentials placed in .env.
CREATE USER IF NOT EXISTS 'ruma_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_APP';

GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.roles                    TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.permissions              TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.role_permissions         TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.departments              TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.users                    TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.login_attempts           TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.categories               TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.priorities               TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.sla_policies             TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.settings                 TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.ticket_sequences         TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.tickets                  TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.ticket_comments          TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.ticket_attachments       TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.ticket_status_history    TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.notifications            TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.notification_preferences TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ruma_itsm.email_queue              TO 'ruma_app'@'localhost';

-- Audit tables: read and append only. No UPDATE, no DELETE.
GRANT SELECT, INSERT ON ruma_itsm.audit_logs     TO 'ruma_app'@'localhost';
GRANT SELECT, INSERT ON ruma_itsm.db_change_logs TO 'ruma_app'@'localhost';
GRANT SELECT         ON ruma_itsm.audit_chain_head TO 'ruma_app'@'localhost';

FLUSH PRIVILEGES;
