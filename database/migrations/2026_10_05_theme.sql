-- Per-person display theme: 'system' follows the device, 'light' = day, 'dark' = night-shift mode.
ALTER TABLE users ADD COLUMN theme ENUM('system','light','dark') NOT NULL DEFAULT 'system' AFTER notification_sound;
