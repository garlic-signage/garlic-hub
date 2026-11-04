CREATE TABLE `user_main` (
     `UID` INTEGER PRIMARY KEY,
     `company_id` INTEGER NOT NULL DEFAULT 1,
     `last_access` TIMESTAMP DEFAULT NULL,
     `login_time` TIMESTAMP DEFAULT NULL,
     `num_logins` INTEGER NOT NULL DEFAULT 0,
     `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     `status` INTEGER NOT NULL DEFAULT 0,
     `locale` CHAR(5) COLLATE NOCASE DEFAULT NULL,
     `username` CHAR(50) COLLATE NOCASE DEFAULT '',
     `password` CHAR(60) DEFAULT NULL,
     `session_id` VARCHAR(60) DEFAULT NULL,
     `gender` VARCHAR(10) COLLATE NOCASE DEFAULT NULL,
     `email` VARCHAR(100) COLLATE NOCASE NOT NULL DEFAULT '',
     `last_password_change` TIMESTAMP DEFAULT NULL,
     UNIQUE (`email`)
);

CREATE TABLE `user_acl` (
    `UID` INTEGER,
    `acl` INTEGER NOT NULL DEFAULT 0,
    `module` VARCHAR(20) DEFAULT NULL,
    FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE
);

CREATE TABLE user_tokens (
     token BLOB(32) PRIMARY KEY,
     UID INTEGER NOT NULL,
     purpose TEXT NOT NULL,
     expires_at TIMESTAMP NOT NULL,
     used_at TIMESTAMP,
     additional_data TEXT,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     FOREIGN KEY (UID) REFERENCES user_main(UID) ON DELETE CASCADE
);

CREATE INDEX idx_token_user_purpose ON user_tokens (UID, purpose);
CREATE INDEX idx_token_expires ON user_tokens (expires_at);

CREATE TABLE oauth2_clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id TEXT UNIQUE NOT NULL,
    client_name TEXT UNIQUE NOT NULL,
    client_secret TEXT DEFAULT NULL,
    redirect_uri TEXT NOT NULL,
    grant_type TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO oauth2_clients (client_id, client_name, client_secret, redirect_uri, grant_type)
VALUES ('edge-default-client', 'The Default Client', '$2y$10$GNIvEOnYy5OxEfdnMO0O0O2g1myLht2CTK4SaVfMK664O85Sd4MA6',
        'https://oauth2client.ddev.site/callback.php', 'authorization_code refresh_token');

CREATE TABLE oauth2_credentials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL, -- 'auth_code', 'access_token', 'refresh_token'
    token TEXT NOT NULL,
    client_id TEXT NOT NULL,
    UID INTEGER NOT NULL,
    redirect_uri TEXT DEFAULT NULL,
    scopes TEXT DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked INTEGER DEFAULT 0,
    FOREIGN KEY (UID) REFERENCES user_main(UID) ON DELETE CASCADE
);

CREATE TABLE oauth2_scopes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scope TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL
);

CREATE TABLE oauth2_client_consent (
     UID INT NOT NULL,
     client_id VARCHAR(255) NOT NULL,
     consented_at DATETIME NOT NULL,
     PRIMARY KEY (UID, client_id)
);

CREATE TABLE mediapool_nodes (
    node_id INTEGER PRIMARY KEY AUTOINCREMENT,
    root_id INTEGER NOT NULL DEFAULT 0,
    parent_id INTEGER NOT NULL DEFAULT 0,
    level INTEGER NOT NULL,
    root_order INTEGER NOT NULL,
    is_user_folder INTEGER NOT NULL DEFAULT 0,
    lft INTEGER NOT NULL DEFAULT 0,
    rgt INTEGER NOT NULL DEFAULT 0,
    UID INTEGER NOT NULL DEFAULT 0,
    visibility INTEGER NOT NULL DEFAULT 0,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    create_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `name` TEXT COLLATE NOCASE NOT NULL DEFAULT '',
    media_location TEXT COLLATE NOCASE NOT NULL CHECK(media_location IN ('internal', 'dropbox', 'azure', 'google', 'webdav', 's3',
                                                          'ftp')) DEFAULT `internal`,
    credentials TEXT
);
CREATE INDEX idx_mediapool_nodes_root_id ON mediapool_nodes (root_id);
-- set some default root dirs.
INSERT INTO mediapool_nodes (root_id, parent_id, level, root_order, lft, rgt, UID, visibility, last_updated, create_date, name)
VALUES (1, 0, 1, 1, 1, 12, 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'public');

-- set some default dirs under public
INSERT INTO mediapool_nodes (root_id, parent_id, level, root_order, lft, rgt, UID, visibility, last_updated, create_date, name)
VALUES (1, 1, 2, 1, 2, 3, 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'images');
INSERT INTO mediapool_nodes (root_id, parent_id, level, root_order, lft, rgt, UID, visibility, last_updated, create_date, name)
VALUES (1, 1, 2, 1, 4, 5, 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'videos');
INSERT INTO mediapool_nodes (root_id, parent_id, level, root_order, lft, rgt, UID, visibility, last_updated, create_date, name)
VALUES (1, 1, 2, 1, 6, 7, 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'widgets');
INSERT INTO mediapool_nodes (root_id, parent_id, level, root_order, lft, rgt, UID, visibility, last_updated, create_date, name)
VALUES (1, 1, 2, 1, 8, 9, 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'documents');
INSERT INTO mediapool_nodes (root_id, parent_id, level, root_order, lft, rgt, UID, visibility, last_updated, create_date, name)
VALUES (1, 1, 2, 1, 10, 11, 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'firmware');

CREATE TABLE mediapool_files (
     media_id BLOB(16) PRIMARY KEY, -- UUID as 16-byte BLOB
     node_id INTEGER NOT NULL,
     deleted INTEGER NOT NULL DEFAULT 0,
     UID INTEGER NOT NULL DEFAULT 0,
     upload_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
     checksum BLOB(32) NOT NULL, -- SHA-256 Hash of the file content
     mimetype VARCHAR(50) COLLATE NOCASE NOT NULL,
     metadata TEXT DEFAULT NULL, --json encoded metadata
     tags TEXT DEFAULT NULL,
     filename TEXT COLLATE NOCASE DEFAULT NULL,
     extension varchar(10) DEFAULT NULL,
     thumb_extension varchar(10) DEFAULT NULL,
     config_data TEXT DEFAULT NULL,
     media_description TEXT DEFAULT NULL
);

CREATE INDEX idx_mediapool_node_id ON mediapool_files (node_id, deleted);
CREATE INDEX idx_mediapool_checksum ON mediapool_files (checksum);

CREATE TABLE playlists (
    playlist_id INTEGER PRIMARY KEY AUTOINCREMENT,
    UID INTEGER NOT NULL DEFAULT 0,
    time_limit INTEGER NOT NULL DEFAULT 0,
    owner_duration INTEGER NOT NULL DEFAULT 0,
    duration INTEGER NOT NULL DEFAULT 0,
    filesize INTEGER NOT NULL DEFAULT 0,
    shuffle INTEGER NOT NULL DEFAULT 0,
    shuffle_picking INTEGER NOT NULL DEFAULT 0,
    export_time TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
    last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    playlist_mode TEXT COLLATE NOCASE DEFAULT 'master' CHECK (playlist_mode IN ('master', 'internal', 'external', 'multizone', 'channel')),
    playlist_name varchar(100) COLLATE NOCASE DEFAULT NULL,
    external_playlist_link  varchar(100) DEFAULT NULL,
    multizone TEXT DEFAULT NULL
);

CREATE TRIGGER update_playlists_last_update
    AFTER UPDATE ON playlists
BEGIN
    UPDATE playlists
    SET last_update = CURRENT_TIMESTAMP
    WHERE playlist_id = NEW.playlist_id;
END;

CREATE TABLE playlists_items (
    item_id INTEGER PRIMARY KEY AUTOINCREMENT,
    playlist_id INTEGER NOT NULL DEFAULT 0,
    UID INTEGER NOT NULL DEFAULT 0,
    flags INTEGER NOT NULL DEFAULT 0, -- flags like disabled in edge and locked, loggable
    item_duration INTEGER NOT NULL DEFAULT 0,
    item_filesize INTEGER NOT NULL DEFAULT 0,
    item_order INTEGER NOT NULL DEFAULT 0,
    last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    item_type CHAR(12) NOT NULL DEFAULT 'mediapool', -- enum in Mariadb 'mediapool', 'media_url', 'playlist', 'playlist_url', 'template', 'channel'
    file_resource BLOB(16) NOT NULL DEFAULT '', -- file or symlink name or numeric ID for media and templates
    datasource VARCHAR(8) COLLATE NOCASE DEFAULT 'file' CHECK (datasource IN ('file', 'stream', 'video_in')),
    mimetype VARCHAR(50) COLLATE NOCASE NOT NULL DEFAULT '',
    item_name TEXT NOT NULL DEFAULT '',
    conditional TEXT NOT NULL DEFAULT '',
    properties TEXT NOT NULL DEFAULT '', -- scaling, position, name
    categories TEXT NOT NULL DEFAULT '',
    content_data TEXT NOT NULL DEFAULT '', -- depends on item and media type: can be url or Widget parameters
    begin_trigger TEXT NOT NULL DEFAULT '',
    end_trigger TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (playlist_id) REFERENCES playlists(playlist_id) ON DELETE CASCADE
);

CREATE TRIGGER update_playlists_on_insert
    AFTER INSERT ON playlists_items
BEGIN
    UPDATE playlists_items
    SET last_update = CURRENT_TIMESTAMP
    WHERE
        item_id = NEW.item_id;

    UPDATE playlists
    SET last_update = CURRENT_TIMESTAMP
    WHERE
        playlist_id = NEW.playlist_id;
END;
CREATE TRIGGER update_playlists_on_update
    AFTER UPDATE ON playlists_items
BEGIN
    UPDATE playlists_items
    SET last_update = CURRENT_TIMESTAMP
    WHERE
        item_id = NEW.item_id;

    UPDATE playlists
    SET last_update = CURRENT_TIMESTAMP
    WHERE
        playlist_id = NEW.playlist_id;
END;
CREATE TRIGGER update_playlists_on_delete
    AFTER DELETE ON playlists_items
BEGIN
    -- no item_id on delete
    UPDATE playlists
    SET last_update = CURRENT_TIMESTAMP
    WHERE
        playlist_id = OLD.playlist_id;
END;

CREATE INDEX idx_playlist_id ON playlists_items (playlist_id, item_order);
CREATE INDEX idx_item_type_resource ON playlists_items (item_type, file_resource);

CREATE TABLE player (
    player_id INTEGER PRIMARY KEY AUTOINCREMENT,
    playlist_id INTEGER NOT NULL DEFAULT 0,
    UID INTEGER NOT NULL DEFAULT 0,
    last_access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status INTEGER NOT NULL DEFAULT 1,
    refresh INTEGER NOT NULL DEFAULT 300,
    licence_id INTEGER NOT NULL DEFAULT 1,
    model INTEGER NOT NULL DEFAULT 0,
    is_intranet BOOLEAN DEFAULT FALSE,
    uuid BLOB(16) NOT NULL,
    api_endpoint TEXT NOT NULL DEFAULT '',
    commands TEXT NOT NULL DEFAULT '', -- set in mysql
    reports TEXT NOT NULL DEFAULT '', -- set in mysql
    firmware TEXT NOT NULL DEFAULT '',
    player_name TEXT NOT NULL DEFAULT '',
    location_data TEXT DEFAULT NULL,
    location_longitude TEXT DEFAULT NULL,
    location_latitude TEXT DEFAULT NULL,
    categories TEXT DEFAULT NULL,
    properties TEXT DEFAULT NULL, -- content-url, width, height, volume, brightness, timezone, etc
    remote_administration TEXT DEFAULT NULL,
    screen_times TEXT DEFAULT NULL
);

CREATE INDEX uuid ON player (uuid);
CREATE INDEX UID ON player (UID);

CREATE TABLE IF NOT EXISTS player_tokens (
    token_id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL,
    access_token TEXT NOT NULL,
    token_type VARCHAR(50) DEFAULT 'Bearer',
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES player(player_id) ON DELETE CASCADE,
    UNIQUE(player_id)
);
