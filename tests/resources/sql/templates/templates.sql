-- needs acl/users.sql: 1 = admin, 2 = anna, 3 = bernd
INSERT INTO templates (template_id, UID, visibility, type, name, content, description)
VALUES
(1, 1, 'public',  'fabric.js', 'Admin Vorlage',  '{"objects":[{"fileName":"abc123.jpg","src":"x"}]}', 'von Admin'),
(2, 2, 'private', 'fabric.js', 'Annas Vorlage',  '{"objects":[]}', NULL),
(3, 3, 'private', 'fabric.js', 'Bernds Vorlage', NULL, NULL);
