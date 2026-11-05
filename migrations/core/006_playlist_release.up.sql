-- the release of a playlist: in_progress after every change, ready when the editor asks for the release,
-- released after the export. Only checked while the release is enabled in the playlist settings
ALTER TABLE playlists ADD COLUMN release_status VARCHAR(12) NOT NULL DEFAULT 'in_progress' CHECK (release_status IN ('in_progress', 'ready', 'released'));
