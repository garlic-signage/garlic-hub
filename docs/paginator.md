# Paginator

The paginator creates the page links and the "elements per page" dropdown of a [datatable](datatable.md).
Two classes work together, both in `App\Framework\Utils\Datatable\Paginator`:

1. **`Builder`** calculates which links exist.
2. **`Preparer`** turns them into the variables of `templates/generic/datatable.mustache`.

The modules do not use them directly. `AbstractDatatableBuilder::createPagination()` and
`createDropDown()` call the builder through `BuildService`, `AbstractDatatablePreparer::preparePagination()`
calls the preparer through `PrepareService`.

## Builder

```php
$builder->configure($currentPage, $itemsPerPage, $totalItems, $usePager, $shortened)
        ->buildPagerLinks();
$links = $builder->getPagerLinks();

$builder->createDropDown(10, 100, 10);
$settings = $builder->getDropDownSettings();      // ['min' => 10, 'max' => 100, 'steps' => 10]
```

- `configure()` corrects invalid values: the page and the items per page are at least 1, the total at least 0.
- `$usePager` adds the arrows `«` (first), `‹` (previous), `›` (next) and `»` (last).
- `$shortened` shows only the pages 1 to 3, the last three pages and the pages two before and after the
  current one.
- A link is `['name' => string, 'page' => int, 'active' => bool]`. Arrows have no `active`.

The datatable calls `createPagination($total, $usePager = true, $isShortened = true)` and
`createDropDown($min = 10, $max = 100, $steps = 10)`. The maximum is
`BaseFilterParametersInterface::MAX_ELEMENTS_PER_PAGE`, which is also the limit for the request parameter.

## Preparer

```php
$preparer->setSite('player')->setBaseFilter($parameters);
$links    = $preparer->prepareLinks($builderLinks);
$dropdown = $preparer->prepareDropdown($dropDownSettings);
```

`PrepareService::configure()` does the `setSite()` and `setBaseFilter()` call with the module name and the
module `Parameters`. The page links keep the current sort column, sort order and elements per page, the
dropdown links keep sort column, sort order and the current page. The `UrlBuilder` adds
`elements_page`, `sort_column`, `sort_order` and `elements_per_page` and, if set, additional URL
parameters. Filter values are not part of the page links, the datatable keeps them in the session.

### `prepareLinks()`

| Key | Content |
|---|---|
| `ELEMENTS_PAGELINK` | URL of the page, e.g. `player?elements_page=2&sort_column=player_id&sort_order=ASC&elements_per_page=10` |
| `ELEMENTS_PAGENAME` | text of the link (`2`, `«`, …) |
| `ELEMENTS_PAGENUMBER` | the page number |
| `ELEMENTS_ACTIVE_PAGE` | `active_page` for the current page, otherwise empty |

### `prepareDropdown()`

One entry per value from `min` to `max` in `steps`:

| Key | Content |
|---|---|
| `ELEMENTS_PER_PAGE_VALUE` | the number |
| `ELEMENTS_PER_PAGE_DATA_LINK` | URL with this number, the JS follows it on change |
| `ELEMENTS_PER_PAGE_NAME` | text of the option |
| `ELEMENTS_PER_PAGE_SELECTED` | `selected` for the current value, otherwise empty |

The values are written to the template with `{{ }}`. The `&` in the URLs becomes `&amp;`, which is
correct in an HTML attribute and is read back by the browser.

## Limits

- Pages are counted from 1.
- The shortened list shows no ellipsis between the groups of pages.
