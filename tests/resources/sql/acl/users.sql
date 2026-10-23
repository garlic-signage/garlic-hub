-- the password of all three users is: Test1234!
-- 1 = admin (module admin of all modules; the ACL bit differs: 8, only users has 2), 2 = user "anna", 3 = user "bernd" without any special rights
INSERT INTO user_main (UID, company_id, status, locale, username, password, email)
VALUES
(1, 1, 3, 'en_US', 'admin', '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'admin@example.com'),
(2, 1, 3, 'en_US', 'anna',  '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'anna@example.com'),
(3, 1, 3, 'en_US', 'bernd', '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'bernd@example.com');

INSERT INTO user_acl (UID, acl, module)
VALUES
(1, 8, 'player'),
(1, 8, 'playlists'),
(1, 8, 'mediapool'),
(1, 8, 'templates'),
(1, 2, 'users'); -- the module users has its own bits: subadmin 1, moduleadmin 2
