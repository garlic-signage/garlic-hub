-- 1 = admin (module admin of all modules), 2 = user "anna", 3 = user "bernd" without any special rights
INSERT INTO user_main (UID, company_id, status, locale, username, password, email)
VALUES
(1, 1, 3, 'en_US', 'admin', 'not-a-real-hash', 'admin@example.com'),
(2, 1, 3, 'en_US', 'anna',  'not-a-real-hash', 'anna@example.com'),
(3, 1, 3, 'en_US', 'bernd', 'not-a-real-hash', 'bernd@example.com');

INSERT INTO user_acl (UID, acl, module)
VALUES
(1, 8, 'player'),
(1, 8, 'playlists'),
(1, 8, 'mediapool'),
(1, 8, 'templates'),
(1, 8, 'users');
