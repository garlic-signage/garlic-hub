# Input, Validation and Escaping

How user input is cleaned, checked and made safe for output. Three jobs, three places:

| Job | Question | Where | Class |
|---|---|---|---|
| Sanitize | Is the value in the expected type and shape? | on input | `Framework\Validation\Sanitizer` |
| Validate | Is the value allowed? | on input | the `Validator` of the module |
| Escape | Is the value safe in this output context? | on output | template engine, `Html` renderers, `Xml::escape()` |

## The convention in short

1. **The database stores the value the user typed.** No `htmlspecialchars` before saving.
   SQL is protected by prepared statements, not by escaping.
2. **The `Sanitizer` normalizes, it does not escape.** Type casts, valid UTF-8, no control characters, trim.
3. **Validation rejects.** Length, format, allowed values. Fields with a fixed set of values
   (locales, token purposes, modes) are compared with a whitelist instead of being escaped.
4. **Escaping happens where the value is written out,** and only the output knows its context.
   Never escape "just in case" earlier. A value escaped twice is shown wrong.

## Why not escape on input

An escaped value is only correct for one context (HTML text). In the database it breaks search,
sorting and length checks (`&` becomes `&amp;`), and every other output (XML, JSON, CSV, mails)
gets entities it does not want. The next escaping step then escapes the entities again.

## Output contexts

### HTML templates (Mustache)

- `{{VARIABLE}}` escapes with `ENT_QUOTES` (set in `config/services/_default.php`). **This is the default.**
- `{{{VARIABLE}}}` writes the value as it is. Use it only for HTML that was built by the code itself
  and is escaped inside, e.g. the form fields from `FormBuilder` (`HTML_ELEMENT`).
- Never use `{{{ }}}` for names, titles, texts, IDs, URLs or anything else that came from a user, the
  database, a player or a file name.
- `LANG_*` translations use `{{ }}` as well. Translations can contain replacements with user data
  (`translate($key, $module, ['{name}' => $userName])`), so they are not trusted. A translation file must
  not contain HTML. Text which needs markup is built in the template.

### Generated HTML (`Framework\Utils\Html`)

The renderers (`TextRenderer`, `DropdownRenderer`, …) build strings. They escape every value with
`AbstractInputFieldRenderer::escape()`. A new renderer must do the same for each value that goes
into an attribute or a text. Their result is passed to the template as `{{{HTML_ELEMENT}}}`.

### XML and SMIL

- XML templates (`templates/player/…`) use `{{VARIABLE}}`. The HTML escape with `ENT_QUOTES`
  produces only entities that are valid in XML.
- Only the XML parts built by the code (`INSERT_ELEMENTS`, `INSERT_PREFETCH_ELEMENTS`,
  `INSERT_PRIORITY_CLASSES`) stay `{{{ }}}`.
- Code which concatenates XML (`Playlists\Helper\ExportSmil`) uses `App\Framework\Helper\Xml::escape()`
  for each value in an attribute or text node. Do not call `htmlspecialchars(…, ENT_XML1)` alone:
  without `ENT_QUOTES` double quotes stay unescaped and end the attribute.

### JavaScript and JSON inside HTML

Do not put values into an inline `<script>` with `{{ }}` or `{{{ }}}` directly. A `"` or `</script>` in the
value would break the script. Build the data in PHP with `App\Framework\Helper\Json::encodeForScript()`
and write the result with `{{{ }}}`:

```html
<script>
	let lang = {{{LANG_JS}}};
</script>
```

The helper hex-escapes `<`, `>`, `&` and `'`. For single values in the HTML (not in a script), use a
`data-` attribute with `{{ }}` and read it with `dataset`.

### URLs

Build query strings with `http_build_query`, not by concatenation. A URL in an `href` or `src` is
additionally escaped by the template.

## Checklist for a new field or template

- [ ] The `Parameters` class gives the field the right `ScalarType`. `RAW` is only for passwords.
- [ ] The `Validator` checks length / format / whitelist.
- [ ] The template uses `{{ }}` for the value.
- [ ] If the value is written into XML by code: `Xml::escape()`.
- [ ] The test of the output contains a value with `<script>"'&`.

## Existing data

Values saved before this convention (until the release which introduced it) are stored HTML-escaped.
They are shown twice escaped (`&amp;amp;`) until the record is saved again. There is no migration
on purpose.
