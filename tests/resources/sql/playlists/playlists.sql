-- needs security/users.sql: 1 = admin, 2 = anna, 3 = bernd
INSERT INTO playlists (playlist_id, UID, time_limit, owner_duration, playlist_mode, playlist_name)
VALUES
(1, 2, 0,   0,   'master',   'Annas Lobby'),
(2, 3, 600, 120, 'internal', 'Bernds Kantine'),
(3, 2, 0,   0,   'master',   'Annas leere Liste');
