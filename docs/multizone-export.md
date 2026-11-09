# Export of a Multizone Playlist

A multizone playlist has no content of its own: its zones show other playlists. The export of a multizone playlist
therefore exports **nothing of its zones**. The players get every zone playlist exactly as it was exported last,
with its own export button.

## Why

- A zone playlist can belong to someone else. A viewer may use a shared playlist as zone, but must not put its
  current, perhaps unfinished state on the players.
- With the release switched on, an editor only reports a playlist as ready and an admin exports it. Exporting the
  zones together with the multizone playlist would release them past this step.

## What the export does

1. It checks the rights on the multizone playlist itself, like every export (edit right, release).
2. For every zone with a playlist it checks that the playlist is usable and that its SMIL files exist
   (`var/smil/playlists/<id>/items.smil`). A zone playlist which was never exported stops the export with the
   message `zone_playlist_not_exported` and its name. Nothing is stored then.
3. The metrics of the multizone playlist (duration, owner duration, file size) are the highest values of the zone
   playlists, taken from their last export.
4. The multizone playlist is marked as released and its version is recorded. The zone playlists keep their files,
   export time and release status.

## For users

Who changes a zone playlist exports it first (or reports it for release), then the multizone playlist.

The code is in [ExportService](../src/Modules/Playlists/Services/ExportService.php), the tests in
`tests/Integration/Modules/Playlists/Services/ExportServiceTest.php`.
