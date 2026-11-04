-- who changed what: rights, shares, settings, users and failed logins. Only written while the
-- audit log and the scheduler (which deletes the old entries) are enabled
CREATE TABLE `audit_log` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `created_at` DATETIME NOT NULL,
    `UID` INTEGER NOT NULL DEFAULT 0,
    `module` VARCHAR(20) NOT NULL DEFAULT '',
    `action` VARCHAR(30) NOT NULL DEFAULT '',
    `unit_id` VARCHAR(100) NULL,
    `target_UID` INTEGER NULL,
    `details` TEXT NULL
);

CREATE INDEX idx_audit_created ON audit_log (created_at);
