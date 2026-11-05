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
2. Middleware: routing → authentication → locale. The session is started when the container creates it.
   Controllers get everything they need (session, translator, config …) by constructor injection, not from request attributes.
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
| `.htaccess`                        | Forwards requests to `public/` when Apache serves the project root |
| `dev-install.sh`                   | Development setup: `var/` directories, `APP_SECRET`, keys, migrations |
| `getloc.sh`                        | Counts lines of code with `cloc`                                |
| `sbom.json`                        | Software bill of materials (CycloneDX), made by `bin/create-sbom.sh` |

## Directories

### `bin/`
CLI entry points.
- `console.php` — Symfony console, loads the commands from `src/Commands`.
- `migrate-storage.sh` — moves uploaded files from `public/var` to `var/storage`.
- `check-code.sh` — runs the tests with coverage and PHPStan.
- `create-sbom.sh` — writes `sbom.json`.

### `config/`
Wiring of the application.
- `bootstrap.php` — loads `.env`, builds the DI container, returns the Slim or console app.
- `middleware.php` — middleware stack of the web app.
- `route.php` — all routes, grouped into HTML pages, `/api` and `/async`.
- `error_handling.php` — PHP error handler and Slim error middleware (JSON errors for `/api` and `/async`).
- `services/` — DI definitions, one file per module (`_default.php` for framework and shared services).
- `settings/` — INI configuration per module (`config_<module>.ini`): the defaults, an update replaces them. What an admin changes on the settings page is in `var/settings`.

### `docs/`
Project documentation.
- `*.md` — topics like installation, CLI, controllers, DI container, exceptions, OAuth2, threat model.
- `media/` — images for the documentation.

### `dockerapp-configs/`
Configuration files copied into the Docker image: Apache vhost, PHP ini, ImageMagick policy,
`.env` for the Core edition.

### `.ddev/`
Local development environment with DDEV.

### `.github/`
- `workflows/tests.yml` — CI on every push and pull request, one job each: static analysis with the architecture tests, unit tests, integration tests, blackbox tests, coverage, security checks (`composer audit`, secret scan with `.gitleaks.toml`). On `main` the job `badge` writes the coverage badge of the README into the branch `badges` (one commit, replaced on every run).
- `workflows/dockerhub-releases.yml` — Docker Hub images, started by hand.
- `actions/setup-app/` — PHP, Composer packages and environment, shared by the jobs of the tests.
- `dependabot.yml` — weekly updates of the Composer packages and the actions.

### `migrations/`
SQL migrations as `NNN_name.up.sql` / `NNN_name.down.sql`.
- `core/` — schema of the Core edition. The `Runner` takes the directory of the edition (`APP_PLATFORM_EDITION`, empty is `core`).
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
Console commands (e.g. `MigrateCommand`). `schedule:run` (`ScheduleRunCommand`) is started by the cron every 5 minutes (the Docker image sets it up in `entrypoint.sh`, an installation by hand needs the crontab line itself). It ends at once while `Scheduler.enabled` of the system settings is off.

#### `src/Controller/`
Controllers which belong to no module, e.g. `HomeController` (dashboard, legal notice).

#### `src/Framework/`
Technical building blocks without business knowledge.

| Directory          | Contents                                                                    |
|--------------------|-----------------------------------------------------------------------------|
| `Config/`          | `Config` and the INI loader for `config/settings`                           |
| `Controller/`      | Helpers for JSON responses                                                  |
| `Dashboards/`      | Dashboard interface and aggregation for the start page                      |
| `Database/`        | Database access; `Where` and `WhereApplier` for the conditions of a query   |
| `Database/BaseRepositories/` | `SqlBase`, CRUD and find traits, transactions                     |
| `Database/Datatable/` | Query building and filter conditions for datatables                      |
| `Database/Migration/` | Migration runner and its repository                                      |
| `Database/NestedSets/` | Nested set trees (e.g. mediapool folders)                               |
| `Exceptions/`      | Exception hierarchy, incl. `TranslatableModuleException`, `NotFoundException`, `AccessDeniedException` |
| `Helper/`          | Small helpers, e.g. secure (un)serialization                                |
| `Http/`            | `Session` and `Cookie`                                                      |
| `Localization/`    | `Locales/` (current and available locales) and `Translate/` (translator, INI loader, ICU formatter) |
| `Media/`           | Mime types, ffmpeg, media properties, signed media URLs, `FileResponse` for `media.php` and `delivery.php` |
| `Middleware/`      | PSR-15 middleware: locale, CSRF, security headers, JSON errors, final render |
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
- `Audit/` — the audit log: `AuditLog::record()` for rights, shares, settings, users and failed logins, into `audit_log`. The container gives the `NullAuditLog` unless `Audit.enabled` and `Scheduler.enabled` are on; the task `audit.cleanup` of the module System deletes the entries older than `Audit.retention_days`.
- `Layout/` — data of the main layout: main menu, user menu, language switch, flash messages.
- `Versioning/` — `UnitVersions`: the earlier states of units in `unit_versions` (templates: the content of the composer; playlists: the exported state, restored with the item ids, items whose media or nested playlist is gone are left out), off by default (`Versioning.enabled`), the newest `max_versions` per unit stay. The module records the state it replaces and restores it itself; the dialog is `public/js/core/UnitVersionsDialog.js`.
- `Scheduler/` — runs the `ScheduledTask`s which are due (hourly or daily), remembers their last run in `scheduler_runs`. A module has its tasks in `App\Modules\<Module>\Scheduler\<Module>ScheduledTasks`.
- `Settings/` — the settings page: `SettingsSchema`, `SettingField` and `SettingsService`.
- `User/` — the user: `UserEntity`, `UsersService`, tokens and the user repositories.
  - `Repositories/Core|Enterprise` — repositories per edition; Enterprise adds tables.

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
- **`System/`** — the audit log `/system/audit` (a datatable for the module admin of system, readable while the log is off) and the settings page `/system/settings`: a tab per module whose module admin the user is. A module offers settings with a `SettingsSchema` (`Shared/Settings`), found by name as `App\Modules\<Module>\Settings\<Module>SettingsSchema`. Optional subsystems (scheduler, audit log) are off by default, the audit log needs the scheduler.
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
- `Unit/` — unit tests with mocks, mirroring the structure of `src/`. Test suite `unit`.
- `Integration/` — tests against a real database built by the real migrations. Test suite `integration`.
  - `Support/` — base classes (`DatabaseTestCase`, `RepositoryTestCase`, `ContainerTestCase`) and the factory for the real DI container.
  - `Modules/<Module>/Repositories/` — repository tests, group `db`.
  - `Modules/<Module>/Services/` — tests of who sees and may change what through the real services, group `acl`.
- `Blackbox/` — tests over HTTP, suite `blackbox`, group `blackbox`: `Support/MiniDeployment` starts the application with the PHP web server in a temporary directory (own `var/`, own database file), `Support/Browser` is a visitor with cookies. They cover the login, the way of a player (`/smil-index`), the security headers and the rights as a user would try them.
- `Architecture/` — rules for the structure, suite `architecture`: the layers and module dependencies of `src/` (`LayersTest`), the groups of the integration tests (`TestGroupsTest`), a controller for every route (`RoutesTest`), a list of the places where the own JavaScript writes HTML (`JavaScriptSinksTest`) and a ratchet for `@var` assertions on arrays, whose number per file may only go down (`ArrayCastsTest`).
- `resources/` — fixtures: config files, translations, widgets, temporary files.
  - `sql/<topic>/` — SQL scripts which insert test data for integration tests (`acl/` holds the shared users).

See [Coding Standards](docs/coding-standards.md#14-testing-standards) for how to write them.

### `translations/`
One folder per language (`de`, `el`, `en`, `es`, `fr`, `ru`) with one INI file per module.
The file name is also the module name used in `translate()` and in `TranslatableModuleException`.
New keys have to be added to all languages, there is no fallback.

### `var/` (git-ignored)
Runtime data.
- `cache/` — application cache, incl. compiled Mustache templates in `cache/mustache`.
- `settings/` — the settings an admin changed (`config_<module>.ini`, only the changed keys). `OverridingConfigLoader` puts them over the defaults of `config/settings`.
- `keys/` — encryption key and the OAuth2 key pair.
- `logs/` — application logs (`scheduler.log`: the output of the cron); `weblogs/` — web server logs.
- `sessions/` — PHP sessions.
- `smil/` — generated SMIL files: `indexes/` per player, `playlists/` per playlist.
- `storage/` — uploaded and generated files: `mediapool/` (originals, thumbs), `playlists/`, `templates/`.

### `vendor/` (git-ignored)
Composer dependencies.
