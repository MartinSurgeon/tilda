-- =============================================================================
-- RUMA Hospital IT Support & Maintenance Reporting System — schema
-- Compatible with MySQL 8.0.16+ and MariaDB 10.4+ (InnoDB, utf8mb4).
--
-- Import order:  schema.sql  ->  triggers.sql  ->  seed.sql
-- Re-running this file DROPS ALL DATA. See database/MIGRATIONS.md.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS db_change_logs, audit_logs, audit_chain_head,
    email_queue, notification_preferences, notifications,
    ticket_status_history, ticket_attachments, ticket_comments, tickets, ticket_sequences,
    settings, sla_policies, priorities, categories,
    login_attempts, users, departments, role_permissions, permissions, roles;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Access control
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(40)  NOT NULL,
    name        VARCHAR(80)  NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(80)  NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       TINYINT UNSIGNED  NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    code       VARCHAR(20)  NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_name (name),
    UNIQUE KEY uq_departments_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users are never hard-deleted (audit history references them); deactivate instead.
CREATE TABLE users (
    id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    role_id              TINYINT UNSIGNED  NOT NULL,
    department_id        SMALLINT UNSIGNED NULL,
    full_name            VARCHAR(120) NOT NULL,
    email                VARCHAR(190) NOT NULL,
    phone                VARCHAR(30)  NULL,
    job_title            VARCHAR(100) NULL,
    password_hash        VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 1,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    locked_until         DATETIME     NULL,
    last_login_at        DATETIME     NULL,
    password_changed_at  DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_department (department_id),
    CONSTRAINT fk_users_role       FOREIGN KEY (role_id)       REFERENCES roles (id),
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting / lockout. Operational data, not the audit trail (that is audit_logs).
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(190) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    succeeded    TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_email (email, attempted_at),
    KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Reference data
-- -----------------------------------------------------------------------------
-- Top-level categories have parent_id NULL; sub-categories point at their parent.
CREATE TABLE categories (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id   SMALLINT UNSIGNED NULL,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    icon        VARCHAR(40)  NOT NULL DEFAULT 'tag',
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_parent_name (parent_id, name),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `tone` maps to a fixed, contrast-checked colour set (critical|high|medium|low)
-- instead of free hex values, so admins cannot create unreadable badges and the
-- strict CSP (no inline styles) holds.
CREATE TABLE priorities (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(20)  NOT NULL,
    name        VARCHAR(40)  NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    tone        ENUM('critical','high','medium','low') NOT NULL,
    icon        VARCHAR(40)  NOT NULL,
    sort_order  TINYINT      NOT NULL DEFAULT 0,
    is_default  TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_priorities_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sla_policies (
    id                 TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    priority_id        TINYINT UNSIGNED NOT NULL,
    response_minutes   INT UNSIGNED NOT NULL,
    resolution_minutes INT UNSIGNED NOT NULL,
    warn_percent       TINYINT UNSIGNED NOT NULL DEFAULT 80,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sla_priority (priority_id),
    CONSTRAINT fk_sla_priority FOREIGN KEY (priority_id) REFERENCES priorities (id) ON DELETE CASCADE,
    CONSTRAINT chk_sla_warn CHECK (warn_percent BETWEEN 1 AND 99),
    CONSTRAINT chk_sla_order CHECK (resolution_minutes >= response_minutes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT         NOT NULL,
    updated_by    INT UNSIGNED NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tickets
-- -----------------------------------------------------------------------------
-- One counter row per year gives gap-free, concurrency-safe refs (RUMA-2026-000123).
CREATE TABLE ticket_sequences (
    seq_year   SMALLINT UNSIGNED NOT NULL,
    last_value INT UNSIGNED      NOT NULL DEFAULT 0,
    PRIMARY KEY (seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    ref                  VARCHAR(20)       NOT NULL,
    title                VARCHAR(150)      NOT NULL,
    description          TEXT              NOT NULL,
    category_id          SMALLINT UNSIGNED NOT NULL,
    subcategory_id       SMALLINT UNSIGNED NULL,
    department_id        SMALLINT UNSIGNED NOT NULL,
    location             VARCHAR(120)      NOT NULL DEFAULT '',
    priority_id          TINYINT UNSIGNED  NOT NULL,
    status               ENUM('open','assigned','in_progress','on_hold','resolved','closed','reopened') NOT NULL DEFAULT 'open',
    requester_id         INT UNSIGNED      NOT NULL,
    assignee_id          INT UNSIGNED      NULL,
    response_due_at      DATETIME          NULL,
    resolve_due_at       DATETIME          NULL,
    first_response_at    DATETIME          NULL,
    resolved_at          DATETIME          NULL,
    closed_at            DATETIME          NULL,
    -- SLA clock pauses while On hold: due dates are pushed back by hold time.
    on_hold_since        DATETIME          NULL,
    hold_minutes         INT UNSIGNED      NOT NULL DEFAULT 0,
    sla_warned_at        DATETIME          NULL,
    sla_breach_notified_at DATETIME        NULL,
    created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_ref (ref),
    KEY idx_tickets_status (status),
    KEY idx_tickets_priority (priority_id),
    KEY idx_tickets_assignee (assignee_id, status),
    KEY idx_tickets_requester (requester_id, created_at),
    KEY idx_tickets_department (department_id),
    KEY idx_tickets_category (category_id),
    KEY idx_tickets_created (created_at),
    KEY idx_tickets_updated (updated_at),
    KEY idx_tickets_sla (status, resolve_due_at),
    CONSTRAINT fk_tickets_category    FOREIGN KEY (category_id)    REFERENCES categories (id),
    CONSTRAINT fk_tickets_subcategory FOREIGN KEY (subcategory_id) REFERENCES categories (id),
    CONSTRAINT fk_tickets_department  FOREIGN KEY (department_id)  REFERENCES departments (id),
    CONSTRAINT fk_tickets_priority    FOREIGN KEY (priority_id)    REFERENCES priorities (id),
    CONSTRAINT fk_tickets_requester   FOREIGN KEY (requester_id)   REFERENCES users (id),
    CONSTRAINT fk_tickets_assignee    FOREIGN KEY (assignee_id)    REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_comments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    body        TEXT         NOT NULL,
    is_internal TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_ticket (ticket_id, created_at),
    CONSTRAINT fk_comments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id),
    CONSTRAINT fk_comments_user   FOREIGN KEY (user_id)   REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_attachments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id     INT UNSIGNED NOT NULL,
    comment_id    INT UNSIGNED NULL,
    uploaded_by   INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(80)  NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    sha256        CHAR(64)     NOT NULL,
    is_internal   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attachments_stored (stored_name),
    KEY idx_attachments_ticket (ticket_id),
    CONSTRAINT fk_attachments_ticket  FOREIGN KEY (ticket_id)   REFERENCES tickets (id),
    CONSTRAINT fk_attachments_comment FOREIGN KEY (comment_id)  REFERENCES ticket_comments (id),
    CONSTRAINT fk_attachments_user    FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_status_history (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id   INT UNSIGNED NOT NULL,
    from_status VARCHAR(20)  NULL,
    to_status   VARCHAR(20)  NOT NULL,
    changed_by  INT UNSIGNED NOT NULL,
    note        VARCHAR(500) NOT NULL DEFAULT '',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_history_ticket (ticket_id, created_at),
    KEY idx_status_history_created (created_at),
    CONSTRAINT fk_status_history_ticket FOREIGN KEY (ticket_id)  REFERENCES tickets (id),
    CONSTRAINT fk_status_history_user   FOREIGN KEY (changed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Notifications
-- -----------------------------------------------------------------------------
CREATE TABLE notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    ticket_id  INT UNSIGNED NULL,
    event      VARCHAR(40)  NOT NULL,
    title      VARCHAR(150) NOT NULL,
    body       VARCHAR(500) NOT NULL DEFAULT '',
    url        VARCHAR(255) NOT NULL DEFAULT '',
    read_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_unread (user_id, read_at),
    KEY idx_notifications_user_id (user_id, id),
    CONSTRAINT fk_notifications_user   FOREIGN KEY (user_id)   REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A missing row means "use the default" (in-app on, email on).
CREATE TABLE notification_preferences (
    user_id INT UNSIGNED NOT NULL,
    event   VARCHAR(40)  NOT NULL,
    in_app  TINYINT(1)   NOT NULL DEFAULT 1,
    email   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, event),
    CONSTRAINT fk_notif_prefs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sent by tools/cron-mail.php so web requests never block on SMTP.
CREATE TABLE email_queue (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    to_email   VARCHAR(190) NOT NULL,
    to_name    VARCHAR(120) NOT NULL DEFAULT '',
    subject    VARCHAR(200) NOT NULL,
    body_html  MEDIUMTEXT   NOT NULL,
    body_text  MEDIUMTEXT   NOT NULL,
    status     ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at    DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_email_queue_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Audit (append-only; see triggers.sql and AUDIT.md)
-- No foreign keys on purpose: the trail must survive anything that happens to
-- the rows it describes. JSON is stored as LONGTEXT + JSON_VALID so the exact
-- bytes that were hashed are the bytes that are stored on both MySQL & MariaDB.
-- -----------------------------------------------------------------------------
CREATE TABLE audit_chain_head (
    chain     VARCHAR(30) NOT NULL,
    last_hash CHAR(64)    NOT NULL,
    PRIMARY KEY (chain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NULL,
    user_email  VARCHAR(190)  NULL,
    action      VARCHAR(60)   NOT NULL,
    entity_type VARCHAR(40)   NULL,
    entity_id   VARCHAR(40)   NULL,
    description VARCHAR(500)  NOT NULL DEFAULT '',
    metadata    LONGTEXT      NULL,
    ip_address  VARCHAR(45)   NULL,
    user_agent  VARCHAR(255)  NULL,
    created_at  DATETIME(6)   NOT NULL,
    prev_hash   CHAR(64)      NOT NULL,
    row_hash    CHAR(64)      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_action (action, created_at),
    KEY idx_audit_entity (entity_type, entity_id),
    CONSTRAINT chk_audit_metadata CHECK (metadata IS NULL OR JSON_VALID(metadata))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE db_change_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_name  VARCHAR(64)   NOT NULL,
    record_id   VARCHAR(64)   NOT NULL,
    operation   ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    old_values  LONGTEXT      NULL,
    new_values  LONGTEXT      NULL,
    user_id     INT UNSIGNED  NULL,
    ip_address  VARCHAR(45)   NULL,
    user_agent  VARCHAR(255)  NULL,
    created_at  DATETIME(6)   NOT NULL,
    prev_hash   CHAR(64)      NOT NULL,
    row_hash    CHAR(64)      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_dbchange_created (created_at),
    KEY idx_dbchange_record (table_name, record_id),
    KEY idx_dbchange_user (user_id, created_at),
    CONSTRAINT chk_dbchange_old CHECK (old_values IS NULL OR JSON_VALID(old_values)),
    CONSTRAINT chk_dbchange_new CHECK (new_values IS NULL OR JSON_VALID(new_values))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genesis hashes: 64 zeros.
INSERT INTO audit_chain_head (chain, last_hash) VALUES
    ('audit_logs',     REPEAT('0', 64)),
    ('db_change_logs', REPEAT('0', 64));
