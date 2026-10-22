# Exceptions

How errors are raised, translated, logged and shown to the user.

## The convention in short

1. **Services throw.** A service never turns an error into `0`, `[]`, `false` or `null`.
2. **Catch only to clean up or to react differently.**
   - Clean up: roll back a transaction and rethrow.
   - React differently: an HTML page redirects with a flash message instead of showing an error page.
     Add a comment why.
3. **Errors the user can cause are translatable.** They throw a `TranslatableModuleException`
   (or one of its subclasses) with a translation key, not a finished text.
4. **One place decides the answer.** For `/async` and `/api` the `JsonErrorHandler` sets status,
   language, format and log level. Controllers do not catch for that.

## Hierarchy

All classes are in `App\Framework\Exceptions`.

```
Exception
└── BaseException                       module name, getDetails(), getDetailsAsString()
    ├── CoreException                   module "Core"
    ├── DatabaseException               module "DB"
    ├── FrameworkException              module "Framework"
    ├── UserException                   module "User"
    └── ModuleException                 module name given by the caller
        └── TranslatableModuleException translation key + replacements, shown to the user
            ├── NotFoundException       record does not exist          → 404
            └── AccessDeniedException   no rights for the record       → 403

RuntimeException
└── AuthException                       internal to the auto login, caught there
```

## Which one to throw

| Situation                                                        | Throw                          |
|------------------------------------------------------------------|--------------------------------|
| A record the user asked for does not exist                       | `NotFoundException`            |
| The user has no rights for the record or action                  | `AccessDeniedException`        |
| Any other error the user can cause and should read (invalid input, limit exceeded …) | `TranslatableModuleException` |
| A technical error in a module the user cannot cause (broken data, missing configuration …) | `ModuleException` with a technical English text |
| An error inside the framework (`src/Framework`)                  | `FrameworkException`, `CoreException` or `DatabaseException` |

## TranslatableModuleException

```php
throw new TranslatableModuleException('mediapool', 'filesize_exceeded', ['size' => 12, 'max_size' => 10]);
throw new NotFoundException('users', 'user_not_found', ['UID' => $UID]);
throw new AccessDeniedException('users', 'user_not_deletable', ['UID' => $UID]);
```

| Parameter         | Meaning                                                                      |
|-------------------|------------------------------------------------------------------------------|
| `$moduleName`     | Name of the translation file: `'mediapool'` → `translations/<lang>/mediapool.ini` |
| `$translationKey` | Key in that file                                                             |
| `$replacements`   | Values for the placeholders, written as `{name}` in the text                  |
| `$previous`       | Optional original exception                                                  |

The exception message itself stays technical (`mediapool: filesize_exceeded {"size":12,…}`)
and is meant for the logs. Replacements without a placeholder in the text, like `UID` above,
still end up in the log and help to find the record.

The matching entry in the translation file:

```ini
filesize_exceeded = "Filesize {size} MB exceeds the maximum of {max_size} MB."
```

**New keys go into all six languages** (`de`, `el`, `en`, `es`, `fr`, `ru`). The translator has no
fallback to another language: a missing key gives an empty text (only the `JsonErrorHandler`
falls back to the bare key). Use the English text in every file until it is translated.

## How an error reaches the user

### JSON routes (`/async`, `/api`)

`config/error_handling.php` passes every exception of these routes to
`Framework\Middleware\JsonErrorHandler`. It answers in the format of `JsonResponseHandler::jsonError()`,
`{"success": false, "error_message": "…"}`, which the frontend shows as error message.

| Exception                        | Status | Message for the user                           |
|----------------------------------|--------|------------------------------------------------|
| `NotFoundException`              | 404    | translated                                     |
| `AccessDeniedException`          | 403    | translated                                     |
| `TranslatableModuleException`    | 400    | translated                                     |
| `ModuleException`                | 400    | the exception message                          |
| Slim `HttpNotFoundException`     | 404    | `Route not found`                              |
| Slim `HttpMethodNotAllowedException` | 405 | `Method not allowed`                          |
| `DomainException`                | 400    | `Domain-specific error`                        |
| everything else                  | 500    | `main.unexpected_error`, the real message only with `APP_DEBUG=true` |

Logging: status 500 is logged as `error` with the exception, everything below as `notice`.
If the translation itself fails (e.g. the error happened before the `LocaleMiddleware`),
the handler falls back to the key or a fixed English text, so the original error is never hidden.

### HTML pages

Exceptions on HTML routes end in Slim's error page. When a page should react differently,
the controller catches the exception, translates it and redirects:

```php
try
{
    $this->service->delete($id);
}
// the list page shows the error as flash message instead of an error page
catch (TranslatableModuleException $e)
{
    $this->flash->addMessage('error', $this->translator->translateException($e));
    return $response->withHeader('Location', '/playlists')->withStatus(302);
}
```

`Translator::translateException()` resolves module, key and replacements in one call.

### PHP warnings and notices

`config/error_handling.php` turns every PHP warning and notice into an `ErrorException`,
so it is handled like any other exception. Errors suppressed with `@` are ignored.

## Cleaning up

Catching to roll back is the only reason for a `catch` in a service:

```php
$this->transactions->begin();
try
{
    // several writes
    $this->transactions->commit();
}
catch (Throwable $e)
{
    $this->transactions->rollBack();
    throw $e;
}
```

## BaseException helpers

| Method                 | Returns                                                        |
|------------------------|----------------------------------------------------------------|
| `getModuleName()`      | Module the exception belongs to                                |
| `getDetails()`         | Array with module, message, code, file, line and trace         |
| `getDetailsAsString()` | The same as formatted text, e.g. for logs                      |

## Tests

```php
$this->expectException(NotFoundException::class);
$this->service->loadPlaylistForEdit(999);
```

To check key and replacements, catch the exception in the test and assert
`getModuleName()`, `getTranslationKey()` and `getReplacements()`.
