# Multizone Presets

The zone editor of a multizone playlist offers fixed layouts as a start. The button next to "Add zone" opens a
dialog with a tile per preset, the preview is drawn from the zones in the aspect ratio of the current resolution,
so a portrait screen shows its real result.

On an empty layout the preset is put on the canvas at once. Existing zones are replaced only after a question,
the playlists of the old zones are not taken over. Nothing is stored before the button save: closing the editor
without saving keeps the old layout.

## Shipped presets

| Id                   | Zones (left, top, width, height in percent)          |
|----------------------|------------------------------------------------------|
| `fullscreen`         | 0 0 100 100                                          |
| `two_columns`        | 0 0 50 100 · 50 0 50 100                             |
| `two_rows`           | 0 0 100 50 · 0 50 100 50                             |
| `main_sidebar`       | 0 0 75 100 · 75 0 25 100                             |
| `main_ticker`        | 0 0 100 90 · 0 90 100 10                             |
| `l_layout`           | 0 0 75 90 · 75 0 25 90 · 0 90 100 10                 |
| `header_main_footer` | 0 0 100 10 · 0 10 100 80 · 0 90 100 10               |
| `grid_2x2`           | 0 0 50 50 · 50 0 50 50 · 0 50 50 50 · 50 50 50 50    |

## The file

The presets are in [config/multizone_presets.json](../config/multizone_presets.json), a list in the order of the dialog:

```json
[
	{
		"id": "main_ticker",
		"zones": [
			{"left": 0, "top": 0,  "width": 100, "height": 90, "bgcolor": "#4a6fa5"},
			{"left": 0, "top": 90, "width": 100, "height": 10, "bgcolor": "#b5651d"}
		]
	}
]
```

- **id**: lowercase letters, digits and `_`. It is the key of the name in the section `[multizone_preset_selects]`
  of `translations/<language>/playlists.ini`. A preset without a translation shows its id.
- **zones**: at least one, in percent, so a preset fits every resolution. The editor converts them to pixel of the
  resolution, the first zone is the lowest layer. The zones are named "Zone 1" to "Zone n".
- **bgcolor**: like a saved zone: `#rgb`, `#rrggbb`, `rgb()`/`rgba()` or `transparent`.

[MultizonePresets](../src/Modules/Playlists/Helper/Compose/MultizonePresets.php) checks every preset with the
[ZonesSchema](../src/Modules/Playlists/Helper/Playlists/ZonesSchema.php) of a saved layout, and every zone must lie
inside of the screen. A broken file stops the editor with an error which names the preset, the editor never gets
wrong zones. A new preset needs a name in every language, the unit test `MultizonePresetsTest` checks the shipped file
and its translations.
