CREATE TABLE templates (
     template_id INTEGER PRIMARY KEY AUTOINCREMENT,
     UID INTEGER NOT NULL DEFAULT 0,
     lastmodified DATETIME NOT NULL DEFAULT (datetime('now')),
     create_date DATETIME NOT NULL DEFAULT (datetime('now')),
     visibility TEXT NOT NULL DEFAULT 'private',
     type TEXT NOT NULL DEFAULT 'fabric.js',
     name TEXT NOT NULL DEFAULT '',
     content TEXT DEFAULT NULL,
     description TEXT DEFAULT NULL
);

CREATE INDEX idx_types ON templates (type);

INSERT INTO user_acl (UID, acl, module)
SELECT 1, 8, 'template'
WHERE NOT EXISTS (
    SELECT 1 FROM user_acl WHERE UID = 1 AND module = 'template'
)
  AND EXISTS (
    SELECT 1 FROM user_main WHERE UID = 1
);