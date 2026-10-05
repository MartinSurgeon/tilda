-- Per-user notification sound. NULL = role default (on for IT staff, off for others).
ALTER TABLE users ADD COLUMN notification_sound TINYINT(1) NULL DEFAULT NULL AFTER must_change_password;
