-- two uploads with the same content (same checksum): a join must not double the items
INSERT INTO mediapool_files (media_id, node_id, UID, checksum, mimetype, filename, extension)
VALUES
('media-id-0000001', 1, 2, 'checksum-image', 'image/jpeg',      'bild.jpg',  'jpg'),
('media-id-0000002', 1, 2, 'checksum-image', 'image/jpeg',      'bild2.jpg', 'jpg'),
('media-id-0000003', 1, 2, 'checksum-video', 'video/mp4',       'film.mp4',  'mp4'),
('media-id-0000004', 1, 2, 'checksum-pdf',   'application/pdf', 'doc.pdf',   'pdf');
