-- the module system: who administrates the users today, administrates the settings, too
INSERT INTO user_acl (UID, acl, module)
SELECT UID, 8, 'system' FROM user_acl
WHERE module = 'users' AND (acl & 2) > 0
  AND NOT EXISTS (SELECT 1 FROM user_acl AS existing WHERE existing.UID = user_acl.UID AND existing.module = 'system');
