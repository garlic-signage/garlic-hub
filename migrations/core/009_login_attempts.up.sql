-- the attempts of the login throttle (Auth\LoginThrottle), counted before the password or the code is checked:
-- the database counts parallel requests one after the other, a cache would lose some of them
CREATE TABLE `login_attempts` (
                                  `attempt_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                                  `attempts` INTEGER NOT NULL DEFAULT 0,
                                  `expires_at` INTEGER NOT NULL
);
CREATE INDEX idx_login_attempts_expires_at ON login_attempts (expires_at);
