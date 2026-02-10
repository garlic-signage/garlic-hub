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
