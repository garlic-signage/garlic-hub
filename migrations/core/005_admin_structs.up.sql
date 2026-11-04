-- the module system: who administrates the users today, administrates the settings, too
INSERT INTO user_acl (UID, acl, module)
SELECT UID, 8, 'system' FROM user_acl
WHERE module = 'users' AND (acl & 2) > 0
  AND NOT EXISTS (SELECT 1 FROM user_acl AS existing WHERE existing.UID = user_acl.UID AND existing.module = 'system');
-- the last run of every task of the scheduler: when a task is due again
CREATE TABLE `scheduler_runs` (
                                  `task` VARCHAR(100) NOT NULL PRIMARY KEY,
                                  `last_run_at` DATETIME NOT NULL,
                                  `status` VARCHAR(10) NOT NULL DEFAULT '',
                                  `error` TEXT NULL
);
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

-- the earlier states of a unit (templates first): written only while the versioning is enabled
CREATE TABLE `unit_versions` (
                                 `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                                 `module` VARCHAR(20) NOT NULL,
                                 `unit_id` VARCHAR(100) NOT NULL,
                                 `version` INTEGER NOT NULL,
                                 `UID` INTEGER NOT NULL DEFAULT 0,
                                 `created_at` DATETIME NOT NULL,
                                 `snapshot` TEXT NOT NULL,
                                 UNIQUE (`module`, `unit_id`, `version`)
);
