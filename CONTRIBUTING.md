# Contributing to garlic-hub

Thanks for helping. Issues and pull requests are welcome. The project is in active development, so code and APIs may still change. For bigger changes please open an issue first, so we can agree on the direction before you spend time on it.

Security problems do not belong in a public issue: see [SECURITY.md](SECURITY.md).

## Setup

Local development runs on [DDEV](https://ddev.com), see the [README](README.md#development) and [docs/install.md](docs/install.md).

```bash
ddev start
ddev composer install
```

## Before you open a pull request

Run these two. They are fast (under a minute) and cover what the CI checks on every PR:

```bash
ddev exec vendor/bin/phpstan analyse --no-progress
ddev exec vendor/bin/phpunit --testsuite unit,architecture,integration
```

You do **not** need to run the blackbox suite or the coverage locally; the CI does it. If something is red in the CI and you do not understand why, just push and ask in the PR.

## What the CI checks

A PR into `main` needs these four checks to be green:

| Check | What it does |
|---|---|
| static analysis and architecture | PHPStan level 8, layer and module dependency rules, test group rules |
| unit tests | Fast tests with mocks |
| integration tests (real database) | Real SQLite database built from the real migrations, real services |
| security checks | Secret scan (gitleaks), `composer audit` |

The blackbox tests (the real application behind a web server, asked over HTTP) and the coverage badge run in the CI as well.

## Code style

- PHP 8.4, `declare(strict_types=1);`, PHPStan level 8 without new ignores.
- Tabs for indentation, match the surrounding code (naming, comments, idiom).
- Respect the layers in [ARCHITECTURE.md](ARCHITECTURE.md). The architecture test tells you, if a class reaches into a layer it should not.
- Translations live in `translations/`, user visible text does not belong into code or templates.

## Writing tests

New behaviour and bug fixes come with a test. Which kind:

1. **Integration test (preferred)** in `tests/Integration/`. Real database, real migrations, SQL fixtures in `tests/resources/sql`. Because it uses the real schema, it breaks when the schema changes, which is wanted. Use `#[Group('db')]` for repository tests, `#[Group('acl')]` for "who sees and may do what" tests and `#[Group('io')]` for tests with real files or other system boundaries but without a database.
2. **Unit test** in `tests/Unit/` for pure logic (calculations, mapping, validation) and for real boundaries (filesystem, network, time). Please do not mock your way through a whole service: such tests break with every refactoring and prove little. If you need five mocks, write an integration test instead.
3. **Blackbox test** in `tests/Blackbox/` only for behaviour that is visible over HTTP: redirects, headers, cookies, status codes.

Every test class needs its own `#[Group(...)]` attribute, groups are not inherited by PHPUnit. The architecture test checks this and says which class is missing one.

Useful commands:

```bash
ddev exec vendor/bin/phpunit --testsuite unit
ddev exec vendor/bin/phpunit --testsuite integration --group db
ddev exec vendor/bin/phpunit --testsuite integration --group acl
ddev exec vendor/bin/phpunit --filter SomeTestName
```

## Pull requests

- One topic per PR, a short description of what changes and why.
- Keep commits readable.
- Do not commit secrets, `.env` files or files from `var/`.
- By contributing you agree that your contribution is released under the [AGPL-3.0](LICENSE) like the rest of the project.
