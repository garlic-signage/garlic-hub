-- the password of all users is: Test1234!
-- status: 0 deleted, 1 blocked, 2 not verified, 3 registered
INSERT INTO user_main (UID, company_id, status, locale, username, password, email)
VALUES
(10, 1, 3, 'de_DE', 'regular',    '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'Regular@Example.com'),
(11, 1, 0, 'en_US', 'deleted',    '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'deleted@example.com'),
(12, 1, 1, 'en_US', 'blocked',    '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'blocked@example.com'),
(13, 1, 2, 'en_US', 'unverified', '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'unverified@example.com'),
(14, 1, 3, 'en_US', 'other',      '$2y$04$c/dQwArsjldVSGn.cCEFt.30ydgw6404jlOGxyL5F/EFJMsxP4l4i', 'other@example.com');

INSERT INTO user_acl (UID, acl, module)
VALUES
(10, 2, 'player'),
(10, 1, 'playlists');
