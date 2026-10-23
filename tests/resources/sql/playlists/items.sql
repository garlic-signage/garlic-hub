-- playlist 1: image, video, nested playlist 2, pdf, template 7 (item_order 1..5)
-- playlist 2: nested playlist 3, template 7 (item_order 1..2)
INSERT INTO playlists_items (item_id, playlist_id, UID, item_order, item_duration, item_filesize, item_type, file_resource, mimetype, item_name)
VALUES
(1, 1, 2, 1, 10, 100,  'mediapool', 'checksum-image', 'image/jpeg',      'bild.jpg'),
(2, 1, 2, 2, 30, 1000, 'mediapool', 'checksum-video', 'video/mp4',       'film.mp4'),
(3, 1, 3, 3, 5,  0,    'playlist',  '2',              '',                'Bernds Kantine'),
(4, 1, 2, 4, 15, 50,   'mediapool', 'checksum-pdf',   'application/pdf', 'doc.pdf'),
(5, 1, 2, 5, 8,  20,   'template',  '7',              'image/png',       'Vorlage'),
(6, 2, 3, 1, 3,  0,    'playlist',  '3',              '',                'Annas leere Liste'),
(7, 2, 3, 2, 8,  20,   'template',  '7',              'image/png',       'Vorlage');
