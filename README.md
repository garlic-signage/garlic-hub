[![docker-hub image](https://github.com/garlic-signage/garlic-hub/actions/workflows/docker-image.yml/badge.svg?branch=main)](https://github.com/garlic-signage/garlic-hub/actions/workflows/docker-image.yml)
[![garlic-hub coverage](https://github.com/garlic-signage/garlic-hub/blob/main/misc/coverage.svg)](https://github.com/garlic-signage/garlic-hub/blob/main/misc/coverage.svg)
[![phpstan level](https://github.com/garlic-signage/garlic-hub/blob/main/misc/phpstan-level.svg)](https://github.com/garlic-signage/garlic-hub/blob/main/misc/phpstan-level.svg)
[![php version](https://github.com/garlic-signage/garlic-hub/blob/main/misc/php-version.svg)](https://github.com/garlic-signage/garlic-hub/blob/main/misc/php-version.svg)

# Garlic-Hub: Digital Signage Management

Garlic-Hub manages your digital signage network — devices, content, and playlists — 
from a single self-hosted interface. No SaaS, no per-device fees, no vendor lock-in.


![Garlic-Hub Mediapool Screenshot](docs/media/showcase.gif)

## Part of the GarlicSignage Ecosystem

| Project | Role |
|---|---|
| [garlic-player](https://github.com/garlic-signage/garlic-player) | SMIL media player |
| **garlic-hub** | Device & content management ← you are here |
| [garlic-launcher](https://github.com/garlic-signage/garlic-launcher) | Root-free Android kiosk launcher |
| [garlic-daemon](https://github.com/garlic-signage/garlic-daemon) | systemd-based player maintenance |
| [garlic-proxy](https://github.com/garlic-signage/garlic-proxy) | Proxy for restricted networks |


## Live Demo
To see garlic-hub in action, use the live demo at:

https://garlic-hub.com  
login: admin  
password: Demo1234!  

The environment is regularly deleted and rebuilt.

## Quick Start

- [User Installation Guide](docs/how-tos/install-user.md)
- [Connecting Media Players](docs/how-tos/connect-mediaplayer-user.md)

## Current Features
| Section                      | Status | description                                                                                                                            |
|------------------------------|--------|----------------------------------------------------------------------------------------------------------------------------------------|
| **Core Framework**           | ✅      | Database, migrations, logging, routing, middleware and error handling with SLIM 4                                                      |
| **Initial admin user**       | ✅      | Set initial admin user after installation                                                                                              |
| **User management**          | ✅      | Basic user management                                                                                            |
| **Template Editor (images)** | 🚧       | Template Editor for Images based on fabric.js                                                                                                                  |
| **Authentication**           | ✅      | Session-based login with remember-me functionality and basic OAuth2 token authorization                                                |
| **Media Management**         | ✅      | Hierarchical content organization with multi-source uploads (local, external links, screencasts, camera, stock platforms with API-key) |
| **SMIL Playlists**           | ✅      | Playlist management and export in industry-standard SMIL format                                                                        |
| **Push support**             | ✅      | Push playlist to a local player                                                                                                        |
| **Multi-Zone Content**       | ✅      | Graphic display zone editor                                                                                                            |
| **Conditional Play**         | ✅      | Define datetime conditions for media playback                                                                                          |
| **Trigger**                  | ✅      | Trigger to play media or nested playlists by priority based on Accesskeys, Touch/Click, Datetime, and Network                          |
| **Local Player Support**     | ✅      | Integration with one local media player                                                                                                |
| **Internationalization**     | ✅      | Locale-specific configurations and adaptable UI (English, Spanish, French, Russian, Greek, German)                                     |


## Milestones
[Milestones](https://github.com/garlic-signage/garlic-hub/milestones)

## Tech-Stack
- PHP 8.4 with strict types enabled 
- SLIM 4 Framework
- Phpstan Level 8
- PHPUnit 12
- Vanilla JavaScript and external libraries
- Composer libraries

## Developer Documentation
- [Coding Standards](docs/coding-standards.md)
- [Installation](docs/install.md)
- [Exceptions](docs/exceptions.md)
- [DI-Container](docs/di-container.md)
- [CLI.php—Command Line Interface](docs/cli.md)
- [Api/Oauth2 - API and Oauth2](docs/oauth2.md)
- [User- Administration](docs/user-administration.md)
- [Connect Media Player](docs/connect-media-player.md)
- [Architecture](ARCHITECTURE.md)

# Contributing
PRs and issues are welcome. The project is in active development. Code and APIs may still change.

# License
[Affero GPL v3.0 License](https://www.gnu.org/licenses/agpl-3.0.en.html).

Note: AGPL requires that modifications — including server-side deployments —
be released under the same license.
