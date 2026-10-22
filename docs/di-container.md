# DI Container

garlic-hub uses [PHP-DI](https://php-di.org). All services are defined explicitly in
`config/services/`, one file per module.

## How the container is built

`config/bootstrap.php` builds the container in this order:

1. `Config` — created directly in the bootstrap, because every other definition may need it.
2. `config/services/_default.php` — framework and shared services. It must come first.
3. All other `*.php` files below `config/services/`, found recursively.

The container is built once per request. A service is created on first use and then shared:
every `$container->get(Session::class)` within a request returns the same instance.

## Files

| File              | Defines                                                                    |
|-------------------|----------------------------------------------------------------------------|
| `_default.php`    | Framework and shared services: session, cookie, flash, translator, locales, template engine, logger, database, cache, users, ACL, layout, middleware |
| `<module>.php`    | Everything of one module: controllers, orchestrators, services, repositories, helpers |

The file name is the module name in lowercase: `src/Modules/Playlists` → `playlists.php`.

## Writing a definition

Every file collects its definitions in `$dependencies` and returns it:

```php
<?php
declare(strict_types=1);

use App\Modules\Playlists\Controller\PlaylistsController;
use App\Modules\Playlists\Helper\Playlists\Orchestrator;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[PlaylistsController::class] = DI\factory(function (ContainerInterface $container)
{
    return new PlaylistsController($container->get(Orchestrator::class));
});

return $dependencies;
```

Rules:

1. **Explicit factories.** Every service is created in a `DI\factory()` with `new` and its
   dependencies from `$container->get()`. Autowiring is not used, so the wiring stays readable
   in one place.
2. **Constructor injection only.** Classes get their dependencies by constructor, never from
   request attributes or by calling the container themselves.
3. **Register what is shared, create inline what is not.** A class that is used in several
   places gets its own entry. A small helper that belongs to exactly one service can be created
   with `new` inside that service's factory.
4. **Key by class name.** Use `Foo::class` as key. A few services use a name instead, because
   there are several of the same type:

   | Name                  | Service                                          |
   |-----------------------|--------------------------------------------------|
   | `SqlConnection`       | Doctrine DBAL connection                         |
   | `AppLogger`           | Logger for the application (`var/logs/app.log`)   |
   | `ModuleLogger`        | Logger for the modules                           |
   | `FrameworkLogger`     | Logger for the framework                         |
   | `LocalFileSystem`     | Flysystem for the local file system              |
   | `AuthorizationServer` | OAuth2 server                                    |

## Interfaces

When code depends on an interface, the container binds it to an implementation:

```php
$dependencies[PlaylistUsageFinderInterface::class] = DI\factory(function (ContainerInterface $container)
{
    return new PlayerPlaylistUsageFinder($container->get(PlayerRepository::class));
});
```

Between modules the **implementing** module binds the interface in its own file. Example:
`Playlists\Contracts\PlaylistUsageFinderInterface` is implemented by the Player module, so the
binding is in `player.php`. This keeps the module that defines the interface free of any
knowledge of the other module (see [Architecture](../ARCHITECTURE.md)).

Current bindings:

| Interface                         | Implementation                    | File            |
|-----------------------------------|-----------------------------------|-----------------|
| `TemplateEngine\AdapterInterface` | `MustacheAdapter`                 | `_default.php`  |
| `TemplateEngine\LayoutDataProviderInterface` | `Shared\Layout\LayoutDataProvider` | `_default.php` |
| `Playlists\Contracts\PlaylistUsageFinderInterface` | `Player\Services\PlayerPlaylistUsageFinder` | `player.php` |
| `Templates\Contracts\TemplateUsageCounterInterface` | `Playlists\Services\PlaylistTemplateUsageCounter` | `playlists.php` |

## Controllers and middleware

- **Controllers** are fetched lazily: `resolve([Controller::class, 'method'], $container)` in
  `config/route.php` asks the container only when the route is called.
- **Middleware** is created in `config/middleware.php` and in `config/route.php`
  (`FinalRenderMiddleware` for the HTML route group).

## Console commands

`bin/console.php` uses the same bootstrap. Every class in `src/Commands/` is fetched from
the container and added to the console, so a command needs a definition like any other service.
