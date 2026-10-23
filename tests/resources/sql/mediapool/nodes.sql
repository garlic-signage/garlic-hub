-- needs acl/users.sql: 1 = admin, 2 = anna, 3 = bernd
-- The migration already created the public tree: 1 public (visibility 2) with 2 images, 3 videos, 4 widgets, 5 documents, 6 firmware.
-- anna:  7 root (her user folder) with 8 Fotos and 9 Archiv
-- bernd: 10 root (his user folder) with 11 Privat (visibility 0) and 12 Geteilt (visibility 2 = public)
INSERT INTO mediapool_nodes (node_id, root_id, parent_id, level, root_order, is_user_folder, lft, rgt, UID, visibility, name)
VALUES
(7,  7,  0,  1, 7,  1, 1, 6, 2, 0, 'anna'),
(8,  7,  7,  2, 7,  0, 2, 3, 2, 0, 'Fotos'),
(9,  7,  7,  2, 7,  0, 4, 5, 2, 0, 'Archiv'),
(10, 10, 0,  1, 10, 1, 1, 6, 3, 0, 'bernd'),
(11, 10, 10, 2, 10, 0, 2, 3, 3, 0, 'Privat'),
(12, 10, 10, 2, 10, 0, 4, 5, 3, 2, 'Geteilt');
