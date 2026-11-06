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

-- the identity of a user at his OpenID Connect provider: the subject is stable, the email may change
CREATE TABLE `user_sso` (
                            `UID` INTEGER NOT NULL,
                            `issuer` VARCHAR(255) NOT NULL,
                            `subject` VARCHAR(255) NOT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE (`issuer`, `subject`),
                            FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE
);
