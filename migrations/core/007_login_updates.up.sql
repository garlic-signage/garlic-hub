-- the second factor of the login (TOTP): the secret is encrypted, it counts only after a confirmed code (enabled_at).
-- last_time_slot is the time slot of the last accepted code: a code is not accepted twice
CREATE TABLE `user_two_factor` (
                                   `UID` INTEGER NOT NULL PRIMARY KEY,
                                   `secret` TEXT NOT NULL,
                                   `enabled_at` DATETIME NULL,
                                   `last_time_slot` INTEGER NOT NULL DEFAULT 0,
                                   FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE
);

-- the recovery codes for a lost device: only the hash, every code once
CREATE TABLE `user_recovery_codes` (
                                       `UID` INTEGER NOT NULL,
                                       `code_hash` CHAR(64) NOT NULL,
                                       `used_at` DATETIME NULL,
                                       UNIQUE (`UID`, `code_hash`),
                                       FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE
);

-- the OpenID Connect providers of the login (SSO), each of a company: in Enterprise every customer has his own.
-- The client secret is encrypted
CREATE TABLE `sso_providers` (
                                 `provider_id` INTEGER PRIMARY KEY,
                                 `company_id` INTEGER NOT NULL DEFAULT 1,
                                 `name` VARCHAR(100) NOT NULL,
                                 `issuer` VARCHAR(255) NOT NULL,
                                 `client_id` VARCHAR(255) NOT NULL,
                                 `client_secret` TEXT NOT NULL,
                                 `allowed_domains` VARCHAR(255) NOT NULL DEFAULT '',
                                 `enabled` INTEGER NOT NULL DEFAULT 1,
                                 `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                 UNIQUE (`company_id`, `issuer`)
);

-- the identity of a user at a provider: the subject is stable, the email may change
CREATE TABLE `user_sso` (
                            `UID` INTEGER NOT NULL,
                            `provider_id` INTEGER NOT NULL,
                            `subject` VARCHAR(255) NOT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE (`provider_id`, `subject`),
                            FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE,
                            FOREIGN KEY (`provider_id`) REFERENCES `sso_providers` (`provider_id`) ON DELETE CASCADE
);

-- a template of the bundled pack (resources/templates) has its key, e.g. fabric/menu_portrait:
-- installing the pack again adds only the ones which are not there
ALTER TABLE templates ADD COLUMN seed_key VARCHAR(100) NULL DEFAULT NULL;
CREATE UNIQUE INDEX idx_templates_seed_key ON templates (seed_key);
