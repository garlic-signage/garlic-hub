-- the token column holds the hash in the real application, here readable names are enough
INSERT INTO user_tokens (token, UID, purpose, expires_at, used_at)
VALUES
('tok-valid',   10, 'password_reset', '2999-12-31 23:59:59', NULL),
('tok-used',    10, 'autologin',      '2999-12-31 23:59:59', '2025-01-01 10:00:00'),
('tok-expired', 14, 'password_reset', '2000-01-01 00:00:00', NULL);
