# Architecture and Repository Structure

A quick reference to every directory of this repository and what belongs into it.

## Layers in `src/`

The PHP code is split into three layers. Dependencies point in one direction only:

```
Modules  →  Shared  →  Framework
```

| Layer       | Contains                                                                 | May use            |
|-------------|--------------------------------------------------------------------------|--------------------|
| `Framework` | Technical building blocks without business knowledge (config, DB, HTTP, templates, translation …) | libraries only |
| `Shared`    | Business code every module needs: users, access control (ACL), page layout | Framework        |
| `Modules`   | The features: player, playlists, mediapool, templates, users, account, auth | Shared, Framework, other modules' contracts |

Between modules the direction is `Templates ← Playlists ← Player`. When a module needs to
ask a "higher" module something (e.g. "is this playlist used by a player?"), it defines an
interface in its `Contracts/` folder and the other module implements it. The wiring happens
in `config/services/`.

## Request flow (HTML pages)

1. `public/index.php` loads `config/bootstrap.php` (container, middleware, routes).
2. Middleware: session → authentication → environment (locale, translator) → routing.
3. The controller returns a `PageResponse` with the page data.
4. `FinalRenderMiddleware` renders the content template into `templates/layouts/main_layout.mustache`,
   using `Shared/Layout` for menus, language switch and flash messages.
   Routes below `/async` and `/api` do not pass this middleware and answer with JSON.

## Root files

| File                               | Purpose                                                         |
|------------------------------------|-----------------------------------------------------------------|
| `README.md`                        | Project overview and quick start                                |
| `ARCHITECTURE.md`                  | This file                                                       |
| `SECURITY.md`                      | How to report security issues                                   |
| `LICENSE`                          | AGPL-3.0                                                        |
| `composer.json` / `composer.lock`  | PHP dependencies                                                |
| `phpunit.xml`                      | PHPUnit configuration                                           |
| `phpstan.neon`                     | PHPStan configuration (level 8, `src` and `tests`)              |
| `.env.dist`                        | Template for the environment file `.env`                        |
| `Dockerfile` / `entrypoint.sh` / `.dockerignore` | Production image and its start script          |
| `docker-compose.yaml`              | Local container stack                                           |
| `Caddyfile`                        | Web server configuration for FrankenPHP/Caddy                   |
| `.htaccess`                        | Forwards requests to `public/` when Apache serves the project root |
| `dev-install.sh`                   | Creates the runtime directories below `var/` for development    |
| `getloc.sh`                        | Counts lines of code with `cloc`                                |
| `preCommit.sh`                     | Runs tests with coverage, PHPStan, coverage badge and SBOM before a commit |
| `sbom.json`                        | Software bill of materials (CycloneDX)                          |

## Directories

### `bin/`
CLI entry points.
- `console.php` — Symfony console, loads the commands from `src/Commands`.
- `migrate-storage.sh` — moves uploaded files from `public/var` to `var/storage`.

### `config/`
Wiring of the application.
- `bootstrap.php` — loads `.env`, builds the DI container, returns the Slim or console app.
- `bootstrap_dev.php` — additions for `APP_ENV=dev`.
- `middleware.php` — middleware stack of the web app.
- `route.php` — all routes, grouped into HTML pages, `/api` and `/async`.
- `error_handling.php` — PHP error handler and Slim error middleware (JSON errors for `/api` and `/async`).
- `services/` — DI definitions, one file per module (`_default.php` for framework and shared services).
- `settings/` — INI configuration per module (`config_<module>.ini`).

### `docs/`
Project documentation.
- `*.md` — topics like installation, CLI, controllers, DI container, exceptions, OAuth2, threat model.
- `how-tos/` — step-by-step guides (e.g. building a datatable).
- `generic/` — documentation of generic components (e.g. `datatable/paginator.md`).
- `media/` — images for the documentation.

### `dockerapp-configs/`
Configuration files copied into the Docker image: Apache vhost, PHP ini, ImageMagick policy,
`.env` for the Edge edition.

### `.ddev/`
Local development environment with DDEV.

### `.github/`
Dependabot configuration and GitHub Actions workflows (Docker image builds).

### `migrations/`
SQL migrations as `NNN_name.up.sql` / `NNN_name.down.sql`.
- `edge/` — schema of the Edge edition.
- `tests/` — test data.

### `misc/`
Everything that is neither code nor documentation.
- `demo/` — demo database and media for a ready-to-try installation.
- `media/` — source graphics (GIMP) for the player default images in `public/smil/defaults/media`.
- `*.svg` — badges for the README.

### `public/`
Web root. Only what the browser or a player may fetch directly.
- `index.php` — entry point of the web application.
- `media.php` — delivers mediapool files to browsers via signed, expiring URLs (no bootstrap).
- `delivery.php` — delivers original media and rendered template items to players (no bootstrap).
- `css/`, `js/` — styles and ES modules, one folder per module; `external/` holds third-party libraries, `js/core/` shared JS.
- `fonts/` — web fonts for the template editor.
- `images/` — icons, flags, bullets.
- `smil/` — static SMIL files: default playlists, simulations and tests for players.
- `var/` — legacy location of uploads, being replaced by `var/storage` (git-ignored).
- `clover/` — generated coverage report (git-ignored).

### `src/`
Application code, namespace `App\`.

#### `src/Commands/`
Console commands (e.g. `MigrateCommand`).

#### `src/Controller/`
Controllers which belong to no module, e.g. `HomeController` (dashboard, legal notice).

#### `src/Framework/`
Technical building blocks without business knowledge.

| Directory          | Contents                                                                    |
|--------------------|-----------------------------------------------------------------------------|
| `Config/`          | `Config` and the INI loader for `config/settings`                           |
| `Controller/`      | Helpers for JSON responses                                                  |
| `Dashboards/`      | Dashboard interface and aggregation for the start page                      |
| `Database/`        | Database access                                                             |
| `Database/BaseRepositories/` | `SqlBase`, CRUD and find traits, transactions                     |
| `Database/Datatable/` | Query building and filter conditions for datatables                      |
| `Database/Migration/` | Migration runner and its repository                                      |
| `Database/NestedSets/` | Nested set trees (e.g. mediapool folders)                               |
| `Exceptions/`      | Exception hierarchy, incl. `TranslatableModuleException`, `NotFoundException`, `AccessDeniedException` |
| `Helper/`          | Small helpers, e.g. secure (un)serialization                                |
| `Http/`            | `Session` and `Cookie`                                                      |
| `Localization/`    | `Locales/` (current and available locales) and `Translate/` (translator, INI loader, ICU formatter) |
| `Media/`           | Mime types, ffmpeg, media properties, signed media URLs                     |
| `Middleware/`      | PSR-15 middleware: session, environment, JSON errors, final render          |
| `OAuth2/`          | Entities and repositories for the OAuth2 server                             |
| `Security/`        | `Crypt` and `CsrfToken`                                                     |
| `Services/`        | Base classes for services and datatable services                            |
| `System/`          | Shell execution and system statistics                                       |
| `TemplateEngine/`  | Mustache adapter, `PageResponse`, `LayoutDataProviderInterface`             |
| `Utils/`           | UI building blocks                                                          |
| `Utils/Datatable/` | Datatable builder, preparer, paginator, result headers                     |
| `Utils/FormParameters/` | Typed and sanitized form and filter parameters                         |
| `Utils/Forms/`     | Base classes for form creation                                              |
| `Utils/Html/`      | Form fields and their renderers                                             |
| `Utils/Widget/`    | Reader for W3C widget `config.xml`                                          |
| `Validation/`      | `BaseValidator` and `Sanitizer`                                             |
| `Xml/`             | Safe XML reader                                                             |

#### `src/Shared/`
Business code every module needs.
- `Acl/` — uniform access levels (module admin, sub admin, editor, viewer) used by all modules.
- `Layout/` — data of the main layout: main menu, user menu, language switch, flash messages.
- `User/` — the user: `UserEntity`, `UsersService`, tokens and the user repositories.
  - `Repositories/Edge|Core|Enterprise` — repositories per edition; the larger editions add tables.

#### `src/Modules/`
The features. Every module follows the same layout:

| Folder          | Contents                                                                  |
|-----------------|---------------------------------------------------------------------------|
| `Controller/`   | HTTP controllers; `Show*Controller` render pages, the others answer `/async` or `/api` |
| `Services/`     | Business logic, incl. the module's `AclValidator`                         |
| `Repositories/` | Database access                                                           |
| `Helper/<UseCase>/` | Everything for one use case: orchestrator, parameters, validator, form and template preparers |
| `Helper/Datatable/` | The module's list view                                               |
| `Contracts/`    | Interfaces other modules implement (see "Layers")                         |

The modules:

- **`Account/`** — the logged-in user's own account: change password, forced password change, language.
- **`Auth/`** — login, logout, auto login, OAuth2 endpoints, `UserSession` and the `AuthMiddleware` (protects all routes except a list of public ones).
- **`Mediapool/`** — media management.
  - `Helper/Media`, `Helper/Nodes`, `Helper/Uploads` — media files, folder tree, uploads.
  - `Utils/` — handlers per media type (image, video, audio, PDF, widget) and the SSRF check for URL uploads.
- **`Player/`** — media players.
  - `IndexCreation/` — detects the player by user agent and builds its SMIL index.
  - `Helper/Index` — delivers the index to the player.
  - `Helper/NetworkSettings` — connectivity settings of a player.
  - `Helper/PlayerPlaylist` — assigns playlists to players.
  - `Dashboard/` — player tile on the start page.
  - `Entities/`, `Enums/` — player entity and player models.
- **`Playlists/`** — playlists and their items.
  - `Collector/` — assembles the playlist structure for a player; `Contracts/` defines what it builds for.
  - `Helper/Compose` — the playlist editor.
  - `Helper/Items`, `Helper/Trigger`, `Helper/ConditionalPlay`, `Helper/Widgets` — items and their settings.
  - `Helper/Templates` — editing template items inside a playlist.
  - `Helper/Export`, `Helper/ExportSmil` — export of playlists as SMIL files.
  - `Services/InsertItems` — inserting media, playlists or templates as items.
- **`Templates/`** — design templates (canvas, SVG, HTML) for playlists.
  - `Helper/Composer` — template editor and image export.
- **`Users/`** — administration of other users (admin only): list, create, edit, initial admin, password reset tokens.

### `templates/`
Mustache templates, one folder per module.
- `layouts/main_layout.mustache` — the frame of every HTML page.
- `generic/` — shared templates and partials (datatable, edit form, media and playlist selectors).
- `player/index/`, `player/configs/` — SMIL index and player configuration templates.

### `tests/`
PHPUnit tests, namespace `Tests\`.
- `Unit/` — unit tests, mirroring the structure of `src/`.
- `Integration/` — bootstrap for integration tests.
- `resources/` — fixtures: config files, translations, widgets, temporary files.

### `translations/`
One folder per language (`de`, `el`, `en`, `es`, `fr`, `ru`) with one INI file per module.
The file name is also the module name used in `translate()` and in `TranslatableModuleException`.
New keys have to be added to all languages, there is no fallback.

### `var/` (git-ignored)
Runtime data.
- `cache/` — application cache, incl. compiled Mustache templates in `cache/mustache`.
- `keys/` — encryption key and the OAuth2 key pair.
- `logs/` — application logs; `weblogs/` — web server logs.
- `sessions/` — PHP sessions.
- `smil/` — generated SMIL files: `indexes/` per player, `playlists/` per playlist.
- `storage/` — uploaded and generated files: `mediapool/` (originals, thumbs), `playlists/`, `templates/`.

### `vendor/` (git-ignored)
Composer dependencies.
