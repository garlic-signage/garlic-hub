[![tests](https://github.com/garlic-signage/garlic-hub/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/garlic-signage/garlic-hub/actions/workflows/tests.yml)
[![docker-hub image](https://github.com/garlic-signage/garlic-hub/actions/workflows/dockerhub-releases.yml/badge.svg?branch=main)](https://github.com/garlic-signage/garlic-hub/actions/workflows/dockerhub-releases.yml)
[![garlic-hub coverage](https://raw.githubusercontent.com/garlic-signage/garlic-hub/badges/coverage.svg)](https://github.com/garlic-signage/garlic-hub/actions/workflows/tests.yml)
[![phpstan level](https://github.com/garlic-signage/garlic-hub/blob/main/misc/phpstan-level.svg)](https://github.com/garlic-signage/garlic-hub/blob/main/misc/phpstan-level.svg)
[![php version](https://github.com/garlic-signage/garlic-hub/blob/main/misc/php-version.svg)](https://github.com/garlic-signage/garlic-hub/blob/main/misc/php-version.svg)

# Garlic-Hub: Digital Signage Management

Garlic-Hub manages your digital signage network — devices, content and playlists —
from a single self-hosted interface. No SaaS, no per-device fees, no vendor lock-in.

![Garlic-Hub Mediapool Screenshot](docs/media/showcase.gif)

## Part of the GarlicSignage Ecosystem

| Project                                                             | Role                                        |
|---------------------------------------------------------------------|---------------------------------------------|
| [garlic-player](https://github.com/garlic-signage/garlic-player)    | SMIL media player                           |
| **garlic-hub**                                                      | Device and content management ← you are here |
| [garlic-launcher](https://github.com/garlic-signage/garlic-launcher) | Root-free Android kiosk launcher           |
| [garlic-daemon](https://github.com/garlic-signage/garlic-daemon)    | systemd-based player maintenance            |
| [garlic-proxy](https://github.com/garlic-signage/garlic-proxy)      | Proxy for restricted networks               |

## Live Demo

See garlic-hub in action: https://garlic-hub.com

- login: `admin`
- password: `Demo1234!`

The demo is deleted and rebuilt regularly.

## Installation

- [User Installation Guide](https://garlic-signage.com/garlic-hub/installation/)
- [Connecting Media Players](https://garlic-signage.com/garlic-hub/docs/tutorials/connect-media-player/)
- [Proxmox LXC Helper Script](https://forge.canaryblue.org/CanaryBlue-Public/garlic-hub-lxc) (community contribution, maintained by @SB-CanaryBlue)

## Features

| Feature                   | Status | Description                                                                                                             |
|---------------------------|--------|-------------------------------------------------------------------------------------------------------------------------|
| **Media management**      | ✅      | Hierarchical folders, uploads from local files, external links, screencasts, camera and stock platforms (with API key) |
| **Media search**          | 🧪      | Search by tags, name and description in all readable folders, filter by kind of media                                  |
| **SMIL playlists**        | ✅      | Create playlists and export them in the industry-standard SMIL format                                                  |
| **Multi-zone content**    | ✅      | Graphical editor for display zones                                                                                     |
| **Template editor**       | ✅      | Image templates based on fabric.js                                                                                     |
| **Conditional play**      | ✅      | Date and time conditions for media playback                                                                            |
| **Triggers**              | ✅      | Play media or nested playlists by priority, triggered by access keys, touch/click, date/time or network                |
| **Player management**     | ✅      | Any number of players register on first contact; list with filters for online/offline, model and playlist; assign playlists |
| **Local player**          | ✅      | A player on the same machine is connected automatically without registration                                          |
| **Push**                  | ✅      | Push a playlist directly to players reachable over their REST API, e.g. in the intranet                               |
| **User management**       | ✅      | Basic user management with roles per module; initial admin user set up after installation                             |
| **Authentication**        | ✅      | Session login with remember-me and basic OAuth2 token authorization                                                    |
| **Two-factor authentication** | 🧪 | TOTP with Aegis and compatible apps, recovery codes, reset by the admin; can be required for all users |
| **Single sign-on** | 🧪 | OpenID Connect with Google, Entra ID, Okta, Keycloak and other providers, several at once; the first login creates the user without rights |
| **Internationalization**  | ✅      | UI in English, German, Spanish, French, Greek and Russian                                                              |
| **Preview player**        | 🧪      | Preview playlists in the browser with the WASM player in a resizable overlay                                           |
| **Thumbnail preview**     | 🧪      | Enlarged thumbnail of playlist items on hover and touch                                                                |
| **Sharing**               | 🧪      | Share units and mediapool folders with users and grant roles from the context menu                                     |
| **Versioning**            | 🧪      | Exported playlists and saved templates are versioned and can be restored, including the template items of a playlist |
| **Release**               | 🧪      | Editors mark playlists as ready for release, only admins export; status in the list and on the dashboard; off by default |
| **System settings**       | 🧪      | Settings per module, e.g. playlist defaults and mediapool file sizes                                                   |
| **Audit log**             | 🧪      | Log of rights, shares, settings, users, deletions and failed logins; off by default                                   |
| **Scheduler**             | 🧪      | Background jobs run by cron every 5 minutes; off by default                                                            |
| **AI helpers**            | 🧪      | Correct texts, tag media and whole folders, fill a playlist on a theme, generate images; with a local model (e.g. Ollama) or a provider over the OpenAI compatible API, per company or per user; off by default, see [AI Helpers](docs/ai.md) |

✅ released · 🧪 in testing on `main`, not yet in a release

Planned work: [Milestones](https://github.com/garlic-signage/garlic-hub/milestones)

## Development

### Requirements

- PHP 8.4 with the extensions curl, fileinfo, imagick, intl, mbstring, openssl, simplexml and zip
- Composer
- SQLite or MariaDB
- Docker with [DDEV](https://ddev.com) (recommended for local development)

### Quick Start

```bash
git clone https://github.com/garlic-signage/garlic-hub.git
cd garlic-hub
ddev start
ddev composer install
cp .env.dist .env        # then fill in the values
ddev exec bash dev-install.sh
```

Then open https://garlic-hub.ddev.site and create the admin user. The full guide is in [docs/install.md](docs/install.md).

### Tests and Static Analysis

```bash
ddev exec vendor/bin/phpunit                                          # everything except the browser tests
ddev exec vendor/bin/phpunit --testsuite unit                         # fast unit tests with mocks
ddev exec vendor/bin/phpunit --testsuite integration                  # real database, real migrations, real services
ddev exec vendor/bin/phpunit --testsuite integration --group db       # only repository tests
ddev exec vendor/bin/phpunit --testsuite integration --group acl      # only who-sees-and-may-do-what tests
ddev exec vendor/bin/phpunit --testsuite integration --group io       # only tests with real files etc., no database
ddev exec vendor/bin/phpunit --testsuite architecture                 # layers, module dependencies, groups of the tests
ddev exec vendor/bin/phpunit --testsuite blackbox                     # the real application behind a web server, asked over HTTP
ddev exec vendor/bin/phpunit --testsuite browser                      # the JavaScript in a headless Chromium, not in the default run
ddev exec vendor/bin/phpstan analyze
```

`bin/check-code.sh` runs the tests with coverage and the static analysis.

### Tech Stack

- PHP 8.4 with strict types, PHPStan level 8, PHPUnit 12
- [Slim 4](https://www.slimframework.com) with PSR-7/PSR-15 middleware and [PHP-DI](https://php-di.org)
- [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html) with SQL migrations
- [Mustache](https://github.com/bobthecow/mustache.php) templates
- league/oauth2-server, Flysystem, Monolog, phpfastcache, Symfony Console
- Vanilla JavaScript (ES modules) and a few external libraries such as fabric.js

## Developer Documentation

**Start here**
- [Architecture](ARCHITECTURE.md) — layers, request flow and every directory explained
- [Installation](docs/install.md)
- [Coding Standards](docs/coding-standards.md)

**Concepts**
- [Controller](docs/controller.md)
- [Datatables](docs/datatable.md)
- [DI Container](docs/di-container.md)
- [Exceptions](docs/exceptions.md)
- [Input, Validation and Escaping](docs/escaping.md)
- [User Administration](docs/user-administration.md) — roles and access levels
- [API and OAuth2](docs/oauth2.md)
- [CLI](docs/cli.md)
- [AI Helpers](docs/ai.md) — setting up the AI, the helpers, security and how to add one

**How-tos**
- [Paginator](docs/paginator.md)
- [Connect a Media Player](docs/connect-media-player.md)
- [Multizone Presets](docs/multizone-presets.md) — the fixed layouts of the zone editor and their file

## Security

Vulnerability reports: see [SECURITY.md](SECURITY.md).

Before reporting, please read the [Threat Model](docs/threat-model.md). It
documents which properties garlic-hub protects, which behaviours are deliberate
design decisions arising from the constraints of unattended signage hardware,
and what is in and out of scope.

A CycloneDX SBOM is generated for each release and attached to the
[release assets](https://github.com/garlic-signage/garlic-hub/releases).

## Contributing

PRs and issues are welcome. The project is in active development, so code and APIs may still change.
See [CONTRIBUTING.md](CONTRIBUTING.md) for the checks to run before a PR, what the CI requires and how to write tests.

## License

[GNU Affero General Public License v3.0](https://www.gnu.org/licenses/agpl-3.0.en.html)

Note: the AGPL requires that modifications — including server-side deployments —
are released under the same license.
