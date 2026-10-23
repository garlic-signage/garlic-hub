INSERT INTO player_tokens (token_id, player_id, access_token, expires_at, created_at, updated_at)
VALUES
(1, 1, 'valid-token',   '2999-12-31 23:59:59', '2025-01-01 00:00:00', '2025-01-01 00:00:00'),
(2, 2, 'expired-token', '2000-01-01 00:00:00', '2025-01-01 00:00:00', '2025-01-01 00:00:00');
