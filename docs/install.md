# Installation for Developers

This guide sets up a local development environment with [DDEV](https://ddev.com).
For a production installation see the
[User Installation Guide](https://garlic-signage.com/garlic-hub/installation/).

## Requirements

- Docker and DDEV
- Git

DDEV brings PHP 8.4, Apache and MariaDB (see `.ddev/config.yaml`). Without DDEV you need:

- PHP 8.4 with the extensions curl, fileinfo, imagick, intl, mbstring, openssl, simplexml and zip
- Composer
- Apache, Nginx or another PHP capable web server with `public/` as document root
- SQLite or MariaDB

Debian packages for a setup without DDEV:

```bash
apt install curl php-cli php-curl php-imagick php-intl php-mbstring php-simplexml php-zip git unzip
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
APP_PLATFORM_EDITION=edge
```

Then choose a database.

**SQLite** (simplest):

```dotenv
DB_MASTER_DRIVER="PDO_SQLITE"
DB_MASTER_PATH="/var/www/html/var/garlic-hub.sqlite"
```

**MariaDB** of DDEV (DDEV's default credentials):

```dotenv
DB_MASTER_DRIVER="PDO_MYSQL"
DB_MASTER_HOST="db"
DB_MASTER_PORT="3306"
DB_MASTER_NAME="db"
DB_MASTER_USER="db"
DB_MASTER_PASSWORD="db"
```

`APP_SECRET` can stay empty, the installer creates it.

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

## Next steps

- Run the tests: `ddev exec vendor/bin/phpunit`
- Run the static analysis: `ddev exec vendor/bin/phpstan analyze`
- Read the [Architecture](../ARCHITECTURE.md) and the [Coding Standards](coding-standards.md).
