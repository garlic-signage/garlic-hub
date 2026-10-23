updated doc for # Datatables

How the overview pages (players, playlists, templates, users) are built: a filter form, a sortable
result list and a paginator. Every module follows the same structure. The **Player** module is the
reference, copy it when you add a datatable.

## The pieces

All classes of a module datatable live in `src/Modules/<Module>/Helper/Datatable/`, the controller in
`Controller/ShowDatatableController.php`, the service in `Services/<Module>DatatableService.php`.

| Class | Base class (`App\Framework\Utils\Datatable`) | Job |
|---|---|---|
| `ShowDatatableController` | – | Runs the steps below, returns a `PageResponse`. No logic. |
| `Orchestrator` | `AbstractDatatableOrchestrator` | Coordinates builder, service and preparer. Knows the template, CSS and JS of the module. |
| `Parameters` | `Framework\Utils\FormParameters\BaseFilterParameters` | The request parameters: filter fields, sort, page. Sanitizes them and keeps them in the session. |
| `DatatableBuilder` | `AbstractDatatableBuilder` | Describes the table: title, columns, filter form fields, pagination. |
| `DatatableService` | `Framework\Services\AbstractDatatableService` | Loads the rows for the current user and filter. |
| `FilterDefinition` | `Framework\Database\Datatable\FilterDefinition` | Table, joins, selected columns and WHERE conditions for the query. |
| `DatatablePreparer` | `AbstractDatatablePreparer` | Turns rows and structure into the variables of the Mustache template. |

The generic work (pagination, header, form, body elements) is done by `BuildService` and
`PrepareService`, which the builder and the preparer get injected.

## Request flow

`ShowDatatableController::show()` always does the same:

```php
$this->orchestrator->configure();
$this->orchestrator->processSubmittedUserInput($request->getQueryParams());
$this->orchestrator->prepareDataGrid();
$dataGrid     = $this->orchestrator->prepareUITemplate();
$templateData = $this->templateFormatter->preparerUITemplate($dataGrid);
return new PageResponse($templateData);
```

1. **`configure()`** takes the UID from the `UserSession` and calls `Builder::configureParameters($UID)`.
   Outside of the edge edition this adds the module parameters (hook `addModuleParameters()`) and, for
   module admins and sub admins, the owner and company filters.
2. **`processSubmittedUserInput()`** calls `Builder::determineParameters()` and then
   `Service::loadDatatable()`.
   - The table fields are created first, because their sortable columns limit the sort parameter.
   - `BaseFilterParameters::parseInputFilterAllUsers()` restores the last filter from the session,
     parses the query parameters, limits sort and pagination, jumps back to page 1 when a filter
     changed and stores the result in the session again.
   - `loadDatatable()` runs the count and the select query.
3. **`prepareDataGrid()`** lets the builder create title, filter form fields, pagination links and the
   "elements per page" dropdown.
4. **`prepareUITemplate()`** lets the preparer format form, pagination, header and body and adds the
   module template settings. The result is one array for the `DatatableTemplatePreparer`.
5. The `DatatableTemplatePreparer` builds the `main_layout` / `this_layout` structure.
   `FinalRenderMiddleware` renders the `PageResponse` with the layout.

## Parameters and what is limited

`BaseFilterParameters` defines `elements_per_page` (default 10), `elements_page` (1), `sort_column`
(default set by the module, e.g. `player_id`) and `sort_order` (`ASC`). A module adds its filter fields in
`$moduleParameters` and gives a session key to the constructor (`'player_filter'`), so the filter
survives a page change.

The input is limited before it reaches the query:

- `sort_column` has to be a sortable column of the table or the default, otherwise the default is used.
- `sort_order` is `ASC` or `DESC`.
- `elements_per_page` is between 1 and `MAX_ELEMENTS_PER_PAGE` (100), `elements_page` is at least 1.
- A changed filter (not sort or pagination) resets the page to 1.

Strings are sanitized by the `Sanitizer` (see [escaping](escaping.md)), they are not escaped here.

## The service and the filter definition

`AbstractDatatableService` offers `fetchForModuleAdmin()` (all rows) and `fetchForUser()` (rows of one
UID). `loadDatatable()` decides by role which one is used. The player service has branches for sub
admin, editor and viewer which are prepared for the reseller extension but not implemented yet.

The `FilterDefinition` describes the query for the `DatatableQuery`:

- `table()`, `joins()`, `userJoins()` (joins when the user sees only own rows)
- `selects()`, `selectsWithUser()` (adds `username` and `company_id` for admins)
- `whereForFiltering($fields)`: special filters (activity, ids) are written by hand, everything else
  is handled by `FilterConditions::forField()`.

All values go into the query as parameters.

## Builder: table and form

```php
public function createTableFields(): static
{
    $this->buildService->createDatatableField('player_name', true);   // name, sortable
    $this->buildService->createDatatableField('playlist_id', false);
    $this->datatableStructure['header'] = $this->buildService->getDatatableFields();
    return $this;
}
```

- The **header text** is the translation of the field name. It is searched in the language file of the
  module and in `main`. A `HeaderField` can skip the translation (`skipTranslation()`) or use a fixed
  language file (`useSpecificLangModule()`).
- `collectFormElements()` fills `$this->datatableStructure['form']` with
  `buildService->buildFormField([...])` (types in `FieldType`: `TEXT`, `DROPDOWN`, `AUTOCOMPLETE`, …).
  The field `id` and `name` are the parameter names.
- `buildTitle()` sets the page title.

## Preparer: the rows

`prepareTableBody($rows, $fields, $currentUID)` returns one entry per row:

```php
$list['UNIT_ID'] = $player['player_id'];                      // data-id of the row
foreach ($fields as $field)
{
    $resultElements = ['CONTROL_NAME_BODY' => $field->getName()];
    $resultElements['is_link'] = $this->prepareService->getBodyPreparer()->formatLink(...);
    $list['elements_result_element'][] = $resultElements;
}
$list['has_action'][] = $this->prepareService->getBodyPreparer()->formatAction(...);
```

The `BodyPreparer` knows these cell types. The key of the cell decides which block of
`templates/generic/datatable.mustache` is rendered:

| Cell key | Method | Output |
|---|---|---|
| `is_text` | `formatText()` | plain text |
| `is_span` | `formatSpan()` | `<span>` with id, class and title |
| `is_link` | `formatLink()` | `<a>` with href, id, class and title |
| `is_button` | `formatButton()` | `<button>` |
| `is_icon` | `formatIcon()` | `<i>` with icon class and title |
| `is_UID` | `formatUID()` | owner name with `data-uid` |

Row actions (end of the row): `has_action` (`formatAction()`, a button handled by JS), `has_action_link`
(`formatActionLink()`) and `has_delete` (`formatActionDelete()`).

Pass raw values to the formatters. The template escapes them.

## Orchestrator: module settings

Besides the constructor with `UserSession` and `configure()`, a module orchestrator implements
`prepareModuleTemplate()`:

```php
protected function prepareModuleTemplate(): array
{
    return [
        'has_add'        => [],                          // or $this->datatablePreparer->prepareAdd('icon', 'link')
        'template_name'  => 'player/datatable',
        'module_name'    => 'player',
        'additional_css' => ['/css/player/datatable.css'],
        'footer_modules' => ['/js/player/datatable/init.js']
    ];
}
```

Playlists and Templates override `prepareList()` to load additional data for the shown rows
("is the playlist in use"). Templates has `checkRights()`. Users has no context menu, its delete action
is `DELETE /async/users`.

## Template

The module template includes the generic one and adds module specific parts such as the context menu:

```mustache
{{> datatable}}

<template id="playerActionsContextMenuTemplate">…</template>
```

`{{> datatable}}` is `templates/generic/datatable.mustache` (the partial loader points to
`templates/generic`). Filter fields and hidden fields are rendered by the `Html` renderers and written
with `{{{HTML_ELEMENT}}}`. All other variables use `{{ }}`, see [escaping](escaping.md).

## Add a datatable to a module

1. `Parameters`: extend `BaseFilterParameters`, set the module name, the session key and the default
   sort column.
2. `FilterDefinition` and `DatatableService`.
3. `DatatableBuilder` (title, fields, form) and `DatatablePreparer` (rows, context menu).
4. `Orchestrator` with `configure()` and `prepareModuleTemplate()`.
5. `ShowDatatableController`, copied from the Player module.
6. Template `templates/<module>/datatable.mustache`, CSS and JS.
7. Translations in all `translations/*/<module>.ini`:
   - the title which `buildTitle()` translates (`overview`, for users `management`),
   - `add`, if the page has an add link (`prepareAdd()`),
   - `count_search_results` as ICU plural message, e.g.
     `{count, plural, one {1 Player} other {# Players}}`,
   - the names of the table fields and of the filter fields.
8. Wiring: factories for the classes in `config/services/<module>.php` and the route in
   `config/route.php` (`$group->get('/<module>', resolve([ShowDatatableController::class, 'show'], $container))`).
9. Tests: copy the tests of the Player module (`tests/Unit/Modules/Player/Helper/Datatable`).

## Paginator

The links and the "elements per page" dropdown are described in [paginator](paginator.md).
