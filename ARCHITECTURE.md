# Repository Structure Overview
Below is a quick reference to the most relevant files and folders in this repository to help you navigate the codebase.

- Caddyfile — Web server configuration used in containerized deployments.
- Dockerfile — Builds the application image used by Docker.
- docker-compose.yaml — Local development stack (app + services) orchestration.
- entrypoint.sh — Container entrypoint script used by the Docker image.
- dev-install.sh — Developer helper script to bootstrap the environment.
- getloc.sh — Utility to manage/update translation locales.
- preCommit.sh — Local Git pre-commit helper (linters, tests, etc.).
- LICENSE — Project license (AGPL-3.0).
- README.md — This file.
- composer.json / composer.lock — PHP dependencies definition and lock file.
- phpunit.xml — PHPUnit configuration.
- phpstan.neon — PHPStan static analysis configuration.

## Directories
- bin/ — CLI entry points and scripts used by the application.
- config/ — Service definitions and configuration for modules and framework glue
    - Example: config/services/player.php registers Player services, controllers, repositories, and helpers in the DI container.
- docs/ — Project documentation, guides, and assets (e.g., screenshots, GIFs).
- migrations/ — Database migration files to evolve schema over time.
- misc/ — Miscellaneous assets (e.g., generated badges, reports).
- public/ — Public web root served by the web server (index.php, assets, JS/CSS).
- src/ — Application source code organized by Framework and Modules namespaces
    - Modules/Users — User management helpers and repositories
        - Helper/Settings/{Builder,Facade,Parameters,Validator}.php — User settings assembly, access, parameter definitions, and validation.
        - Repositories/Core — Persistent storage for user-centric aggregates (e.g., Stats, Contacts).
        - Repositories/Edge — Storage access for Edge edition user data (Acl, Main, Tokens).
    - Modules/Player — Player-related controllers, services, index creation, and repositories
        - Helper/Datatable — Builder, Parameters, Preparer, and Controller facade for datatable UIs.
        - IndexCreation — Builders to assemble Player index files, template selector, UA handling.
        - Services — Application services including PlayerService, PlayerIndexService, AclValidator.
        - Repositories — Persistence layer (PlayerRepository, PlayerTokenRepository, PlayerIndexRepository).
        - Controller — HTTP controllers like PlayerIndexController, PlayerPlaylistController.
    - Framework — Reusable framework components (routing, DI, utils, translator, etc.).
- templates/ — Server-side templates rendered by the Template Engine.
- tests/ — PHPUnit tests (unit/integration) covering framework and modules.
- translations/ — i18n message catalogs.
- var/ — Runtime cache, logs, and other generated artifacts.
- vendor/ — Composer-installed dependencies.
