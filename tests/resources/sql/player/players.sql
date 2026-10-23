INSERT INTO player (player_id, playlist_id, UID, last_access, last_update, status, refresh, uuid, commands, reports, player_name, location_data, properties, categories, remote_administration, screen_times)
VALUES
(1, 1, 1, '2000-01-01 00:00:00', '2025-03-01 09:00:00', 1, 300, '11111111-1111-4111-8111-111111111111', 'reboot,update', 'screenshot', 'Eingang',   'a:1:{s:4:"city";s:6:"Berlin";}', 'a:2:{s:5:"width";i:1920;s:6:"height";i:1080;}', NULL, NULL, NULL),
(2, 1, 1, '2000-01-01 00:00:00', '2025-03-01 09:00:00', 1, 300, '22222222-2222-4222-8222-222222222222', '',              '',           'Kasse',     NULL, NULL, NULL, NULL, NULL),
(3, 2, 2, '2000-01-01 00:00:00', '2025-03-01 09:00:00', 1, 300, '33333333-3333-4333-8333-333333333333', '',              '',           'Kantine',   NULL, NULL, NULL, NULL, NULL),
(4, 0, 2, '2000-01-01 00:00:00', '2025-03-01 09:00:00', 1, 300, '44444444-4444-4444-8444-444444444444', '',              '',           'Ohne Liste', NULL, NULL, NULL, NULL, NULL);
