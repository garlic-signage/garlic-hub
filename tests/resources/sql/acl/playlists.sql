-- like acl/players.sql: playlist 1 and 2 belong to anna, 3 to bernd, 4 to the admin
INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name)
VALUES
(1, 2, 'master', 'Annas Playlist 1'),
(2, 2, 'master', 'Annas Playlist 2'),
(3, 3, 'master', 'Bernds Playlist'),
(4, 1, 'master', 'Admin Playlist');
