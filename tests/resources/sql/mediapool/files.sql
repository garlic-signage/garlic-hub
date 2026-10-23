-- needs mediapool/nodes.sql
-- 1 and 2 belong to anna (Fotos), 3 to bernd (Privat), 4 to bernd (Geteilt, public), 5 is public (images), 6 is deleted (Fotos)
INSERT INTO mediapool_files (media_id, node_id, deleted, UID, checksum, mimetype, filename, extension, thumb_extension, metadata, upload_time)
VALUES
('11111111-1111-4111-8111-111111111111', 8,  0, 2, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'image/jpeg', 'urlaub.jpg',  'jpg', 'jpg', '{}', '2025-01-01 10:00:00'),
('22222222-2222-4222-8222-222222222222', 8,  0, 2, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'video/mp4',  'film.mp4',    'mp4', 'jpg', '{}', '2025-01-02 10:00:00'),
('33333333-3333-4333-8333-333333333333', 11, 0, 3, 'cccccccccccccccccccccccccccccccc', 'image/png',  'geheim.png',  'png', 'png', '{}', '2025-01-03 10:00:00'),
('44444444-4444-4444-8444-444444444444', 12, 0, 3, 'dddddddddddddddddddddddddddddddd', 'image/png',  'teilen.png',  'png', 'png', '{}', '2025-01-04 10:00:00'),
('55555555-5555-4555-8555-555555555555', 2,  0, 1, 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', 'image/jpeg', 'logo.jpg',    'jpg', 'jpg', '{}', '2025-01-05 10:00:00'),
('66666666-6666-4666-8666-666666666666', 8,  1, 2, 'ffffffffffffffffffffffffffffffff', 'image/jpeg', 'weg.jpg',     'jpg', 'jpg', '{}', '2025-01-06 10:00:00');
