# Installation for Developers

This guide sets up a local development environment with [DDEV](https://ddev.com).
For a production installation see the
[User Installation Guide](https://garlic-signage.com/garlic-hub/installation/).

## Requirements

- Docker and DDEV
- Git

DDEV brings PHP 8.4, Apache, ffmpeg and ImageMagick (see `.ddev/config.yaml`). Without DDEV you need:

- PHP 8.4 with the extensions curl, fileinfo, imagick, intl, mbstring, openssl, simplexml and zip
- Composer
- Apache, Nginx or another PHP capable web server with `public/` as document root;
  Apache needs `mod_rewrite` and `mod_xsendfile` (the Docker image enables both)
- ffmpeg (`ffprobe` reads videos and audio files) and Ghostscript for PDFs
- SQLite or MariaDB

Debian packages for a setup without DDEV:

```bash
apt install curl php-cli php-curl php-imagick php-intl php-mbstring php-simplexml php-zip git unzip ffmpeg ghostscript
```

## Setup

### 1. Clone the repository

```bash
git clone https://github.com/garlic-signage/garlic-hub.git
cd garlic-hub
```

### 2. Start DDEV

```bash
ddev start
```

Customizing `.ddev/config.yaml` is possible, but not required.

### 3. Install the PHP dependencies

```bash
ddev composer install
```

### 4. Create the environment file

```bash
cp .env.dist .env
```

Set at least these values in `.env`:

```dotenv
APP_ENV=dev
APP_DEBUG=true
APP_PLATFORM_EDITION=core
```

Then choose a database.

**SQLite** (simplest):

```dotenv
DB_MASTER_DRIVER="PDO_SQLITE"
DB_MASTER_PATH="/var/www/html/var/garlic-hub.sqlite"
```

SQLite runs in WAL mode: beside `garlic-hub.sqlite` there are the files `garlic-hub.sqlite-wal` and
`garlic-hub.sqlite-shm`, the directory must be writable by the web server. A copy of the `.sqlite` file
alone while the system runs is no backup, it can miss the latest changes. Stop the system and copy
all three files, or let SQLite write the backup while it runs:

```bash
sqlite3 var/garlic-hub.sqlite ".backup var/garlic-hub-backup.sqlite"
```

**MariaDB** of DDEV (DDEV's default credentials). The project's `.ddev/config.yaml` disables the
db container with `omit_containers: [db]`, remove it there and run `ddev restart` first:

```dotenv
DB_MASTER_DRIVER="PDO_MYSQL"
DB_MASTER_HOST="db"
DB_MASTER_PORT="3306"
DB_MASTER_NAME="db"
DB_MASTER_USER="db"
DB_MASTER_PASSWORD="db"
```

`APP_SECRET` can stay empty, the installer creates it.

Optional: `DB_SLOW_QUERY_MS` (e.g. `100`) writes every query which takes longer into `var/logs/slow-queries.log`. Empty is off.

### 5. Run the installer

```bash
ddev exec bash dev-install.sh
```

The script runs inside the container and

- creates the runtime directories below `var/` (cache, logs, sessions, keys, storage),
- creates a random `APP_SECRET` in `.env` if it is empty,
- creates the encryption key and the OAuth2 key pair in `var/keys/` (skipped if they exist),
- runs the database migrations (`bin/console.php db:migrate`).

### 6. Create the admin user

Open https://garlic-hub.ddev.site. As long as `var/install.lock` does not exist, every request
is redirected to `/create-initial`, where you create the first admin user. Saving it writes
`var/install.lock`; after that you can log in.

To start over, delete the database and `var/install.lock` and run the installer again.

## Optional features

The system is lean by default. The module admin of `system` switches these on at `/system/settings`:

- **Scheduler**: runs the background tasks every 5 minutes. The Docker image has the cron line already,
  for ddev add one on the host: `*/5 * * * * cd /path/to/garlic-hub && ddev exec php bin/console.php schedule:run`.
- **Audit log**: needs the scheduler, which deletes the old entries. The log is shown at `/system/audit`.
- **Versioning**: keeps earlier states of templates and exported playlists.

The admin overrides of the settings are written to `var/settings`. `dev-install.sh` does not create it:
`ddev exec mkdir -p var/settings`.

## Next steps

- Run the tests: `ddev exec vendor/bin/phpunit`
- Run the static analysis: `ddev exec vendor/bin/phpstan analyze`
- Read the [Architecture](../ARCHITECTURE.md) and the [Coding Standards](coding-standards.md).
