-- =============================================================================
-- Seed data: roles, permissions, reference data, demo users.
-- All demo users share the password  Ruma@2026!  and must change it on first
-- login. Remove or deactivate demo users before go-live.
-- =============================================================================

SET NAMES utf8mb4;

-- Roles ----------------------------------------------------------------------
INSERT INTO roles (id, slug, name, description) VALUES
 (1, 'employee',   'Employee',          'Reports problems and follows their own tickets.'),
 (2, 'technician', 'IT Technician',     'Works the ticket queue and resolves problems.'),
 (3, 'it_manager', 'IT Manager / Admin','Runs the IT service: users, settings, SLAs, assignment and reports.'),
 (4, 'management', 'Management',        'Read-only dashboards and monthly reports.'),
 (5, 'auditor',    'Auditor',           'Read-only access to the full audit trail.');

-- Permissions ----------------------------------------------------------------
INSERT INTO permissions (id, slug, description) VALUES
 ( 1, 'ticket.create',      'Create tickets'),
 ( 2, 'ticket.view_own',    'View and comment on own tickets'),
 ( 3, 'ticket.view_all',    'View the full ticket queue'),
 ( 4, 'ticket.work',        'Accept tickets, change status, add internal notes and attachments'),
 ( 5, 'ticket.assign',      'Assign unassigned tickets'),
 ( 6, 'ticket.reassign',    'Reassign tickets that already have an owner'),
 ( 7, 'dashboard.overview', 'View organisation-wide dashboard'),
 ( 8, 'report.view',        'View reports'),
 ( 9, 'report.export',      'Export reports to PDF and CSV'),
 (10, 'audit.view',         'View the audit log'),
 (11, 'audit.export',       'Export the audit log'),
 (12, 'audit.verify',       'Run the audit integrity check'),
 (13, 'admin.users',        'Manage users and roles'),
 (14, 'admin.settings',     'Manage categories, departments, priorities, SLAs and settings');

INSERT INTO role_permissions (role_id, permission_id) VALUES
 -- Employee
 (1, 1), (1, 2),
 -- IT Technician
 (2, 1), (2, 2), (2, 3), (2, 4), (2, 5),
 -- IT Manager / Admin
 (3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6), (3, 7), (3, 8), (3, 9),
 (3, 10), (3, 12), (3, 13), (3, 14),
 -- Management (read-only reporting; may still raise their own tickets)
 (4, 1), (4, 2), (4, 7), (4, 8), (4, 9),
 -- Auditor (read-only audit trail)
 (5, 10), (5, 11), (5, 12);

-- Departments ----------------------------------------------------------------
INSERT INTO departments (id, name, code) VALUES
 ( 1, 'Emergency',              'ER'),
 ( 2, 'Intensive Care Unit',    'ICU'),
 ( 3, 'Outpatients',            'OPD'),
 ( 4, 'Radiology',              'RAD'),
 ( 5, 'Laboratory',             'LAB'),
 ( 6, 'Pharmacy',               'PHM'),
 ( 7, 'Maternity',              'MAT'),
 ( 8, 'Theatre & Surgery',      'THR'),
 ( 9, 'Administration',         'ADM'),
 (10, 'Finance',                'FIN'),
 (11, 'Human Resources',        'HR'),
 (12, 'Information Technology', 'IT');

-- Categories (top level) -----------------------------------------------------
INSERT INTO categories (id, parent_id, name, description, icon, sort_order) VALUES
 (1, NULL, 'Hardware',       'Computers, printers, screens and other devices', 'monitor', 1),
 (2, NULL, 'Software',       'Applications and systems not working as expected', 'app',     2),
 (3, NULL, 'Network',        'Internet, Wi-Fi and connection problems',         'wifi',    3),
 (4, NULL, 'Account Access', 'Passwords, locked accounts and permissions',      'key',     4),
 (5, NULL, 'Other',          'Anything else',                                   'help',    5);

-- Sub-categories -------------------------------------------------------------
INSERT INTO categories (parent_id, name, sort_order) VALUES
 (1, 'Desktop computer', 1), (1, 'Laptop', 2), (1, 'Printer or scanner', 3),
 (1, 'Monitor, keyboard or mouse', 4), (1, 'Medical device connection', 5), (1, 'Phone or tablet', 6),
 (2, 'Hospital management system (HMS)', 1), (2, 'Email or Microsoft Office', 2),
 (2, 'Laboratory system (LIS)', 3), (2, 'Radiology / PACS', 4), (2, 'Pharmacy system', 5), (2, 'Other application', 6),
 (3, 'No internet', 1), (3, 'Wi-Fi', 2), (3, 'Slow connection', 3), (3, 'Remote access / VPN', 4),
 (4, 'Forgotten password', 1), (4, 'Account locked', 2), (4, 'New account', 3), (4, 'Change of access rights', 4),
 (5, 'Equipment request', 1), (5, 'General question', 2);

-- Priorities & SLAs ----------------------------------------------------------
INSERT INTO priorities (id, slug, name, description, tone, icon, sort_order, is_default) VALUES
 (1, 'critical', 'Critical', 'Patient care or a whole department is stopped.',       'critical', 'flame',      1, 0),
 (2, 'high',     'High',     'Work is badly affected and there is no workaround.',  'high',     'arrow-up',   2, 0),
 (3, 'medium',   'Medium',   'Work is affected but there is a workaround.',         'medium',   'minus',      3, 1),
 (4, 'low',      'Low',      'Minor issue, question or request.',                   'low',      'arrow-down', 4, 0);

-- Minutes. Critical: respond 15m / resolve 4h. High: 1h / 8h.
-- Medium: 4h / 24h. Low: 8h / 3 days.
INSERT INTO sla_policies (priority_id, response_minutes, resolution_minutes, warn_percent) VALUES
 (1,  15,  240, 75),
 (2,  60,  480, 80),
 (3, 240, 1440, 80),
 (4, 480, 4320, 80);

-- Demo users (password: Ruma@2026!) -------------------------------------------
INSERT INTO users (id, role_id, department_id, full_name, email, job_title, password_hash, must_change_password) VALUES
 (1, 3, 12, 'Sarah Okafor',   'admin@ruma.hospital',      'IT Manager',      '$2y$12$GYT60yzLfj8300nPvsSh.OdbnnV804jYO1ZNdXHZH767kZ35kESTW', 1),
 (2, 2, 12, 'James Boateng',  'tech@ruma.hospital',       'IT Technician',   '$2y$12$GYT60yzLfj8300nPvsSh.OdbnnV804jYO1ZNdXHZH767kZ35kESTW', 1),
 (3, 2, 12, 'Linda Mwangi',   'tech2@ruma.hospital',      'IT Technician',   '$2y$12$GYT60yzLfj8300nPvsSh.OdbnnV804jYO1ZNdXHZH767kZ35kESTW', 1),
 (4, 1,  1, 'Esther Phiri',   'employee@ruma.hospital',   'Charge Nurse',    '$2y$12$GYT60yzLfj8300nPvsSh.OdbnnV804jYO1ZNdXHZH767kZ35kESTW', 1),
 (5, 4,  9, 'Michael Adeyemi','management@ruma.hospital', 'Hospital Director','$2y$12$GYT60yzLfj8300nPvsSh.OdbnnV804jYO1ZNdXHZH767kZ35kESTW', 1),
 (6, 5,  9, 'Ruth Nkansah',   'auditor@ruma.hospital',    'Compliance Officer','$2y$12$GYT60yzLfj8300nPvsSh.OdbnnV804jYO1ZNdXHZH767kZ35kESTW', 1);

-- Settings -------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
 ('org.name',                 'RUMA Hospital'),
 ('org.domain',               'ruma.hospital'),
 ('tickets.ref_prefix',       'RUMA'),
 ('notifications.email',      '1'),
 ('poll.interval_seconds',    '15');
