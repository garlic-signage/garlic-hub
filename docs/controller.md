# Controller

A controller connects a route with the code that does the work. It reads the request,
hands the input over and returns a response. It contains no business logic, no validation
and no database access.

## Where controllers live

- `src/Modules/<Module>/Controller/` — one folder per module.
- `src/Controller/` — controllers that belong to no module (e.g. `HomeController`).
- Routes are registered in `config/route.php`, the controller is built by the DI container
  (see [DI Container](di-container.md)).

## Two kinds of controllers

| Kind              | Naming              | Route group      | Returns                                   |
|-------------------|---------------------|------------------|-------------------------------------------|
| HTML page         | `Show*Controller`   | `''` (default)   | `PageResponse`, or a redirect             |
| JSON endpoint     | `*Controller`       | `/async`, `/api` | JSON via a `ResponseBuilder`              |

### HTML pages

A page controller returns a `PageResponse` with the page data. It does not render anything
itself: `FinalRenderMiddleware` renders the content template and puts it into the main layout
(menus, language switch, flash messages).

```php
public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $templateData = $this->templatePreparer->prepare($this->orchestrator->buildForm());

    return new PageResponse($templateData);
}
```

The page data always has this structure:

```php
[
    'main_layout' => ['LANG_PAGE_TITLE' => '…', 'additional_css' => […], 'footer_modules' => […]],
    'this_layout' => ['template' => 'module/name', 'data' => [/* variables of the template */]]
]
```

`PageResponse` throws a `FrameworkException` right away if `this_layout.template` is missing.

Redirects (e.g. after saving a form) are normal responses:

```php
return $response->withHeader('Location', '/playlists')->withStatus(302);
```

### JSON endpoints

JSON controllers delegate to an orchestrator of the module (`Helper/<UseCase>/Orchestrator`).
The orchestrator validates the input and answers through a `ResponseBuilder`, which extends
`Framework\Controller\BaseResponseBuilder`. The controller only passes the input on:

```php
readonly class PlaylistsController
{
    public function __construct(private Orchestrator $orchestrator) {}

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $answer = $this->orchestrator->setInput($request->getParsedBody())->validate($response);
        if ($answer !== null)
            return $answer;

        return $this->orchestrator->delete($response);
    }
}
```

An orchestrator is used exactly once per request: `setInput()`, then `validate…()`, then the action.
The validation keeps the checked values (e.g. the playlist id) for the action.

Every JSON answer has the format of `JsonResponseHandler`:
`{"success": true, …}` or `{"success": false, "error_message": "…"}`.

## Rules

1. **Signature.** Every action has the form
   `(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface`.
   `$args` holds the route placeholders, e.g. `{playlist_id}`.
2. **Dependencies by constructor.** Everything a controller needs (orchestrator, session,
   translator, flash messages …) is injected by the constructor. Request attributes are not used.
   Prefer `readonly` classes with constructor property promotion.
3. **Few dependencies.** One orchestrator is the goal. More than four or five dependencies
   show that the controller does too much — move the work into an orchestrator.
4. **No logic.** Allowed is only reading the input and simple casts, e.g.
   `(int) ($args['playlist_id'] ?? 0)`. Sanitizing and validating happens in the
   `Parameters` and `Validator` classes of the module, the work in services.
5. **No exception handling by default.** Services throw. For `/async` and `/api` the
   `JsonErrorHandler` turns every exception into a JSON error with the right status
   (`NotFoundException` → 404, `AccessDeniedException` → 403, other
   `TranslatableModuleException` → 400, plain `ModuleException` → 400, everything else → 500)
   and logs it.
   Catch an exception only if the controller has to react differently, e.g. an HTML page
   that redirects with a flash message instead of showing an error page. Add a comment why.
   See [Exceptions](exceptions.md).

```php
try
{
    $playlist = $this->playlistsService->loadPlaylistForEdit($playlistId);
}
// the compose page redirects to the list with a message instead of an error page
catch (NotFoundException)
{
    return $this->redirectWithErrors($response, 'Playlist not found.');
}
```

## Tests

Controller tests check what comes out, not how the response object is used internally:

- Page actions: `assertInstanceOf(PageResponse::class, $result)` and the getters
  `getTemplate()`, `getTemplateData()`, `getMainLayoutData()`.
- Redirects and JSON: status code, `Location` header or decoded body.
- Pass stubs for the dependencies into the constructor. If the controller must not touch
  the response, say so: `$responseMock->expects($this->never())->method($this->anything())`.
