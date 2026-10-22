# Installation instructions for Developer

## Prerequisites
Ensure your environment meets the following requirements:
- **PHP** 8.4 or higher
- **Composer** for dependency management
- **Web Server**: Apache, Nginx, or other PHP-compatible server
- **Database**: MariaDB or SQLite for smaller deployments (compatible with other SQL databases)
- **Docker** (optional) for local development with ddev

### Debian Packages needed:
```bash
apt install curl php-http php-zip php-simplexml php-curl php-intl php-imagick php-cli php-mbstring git unzip
```

## Step-by-Step Guide

1. **Clone the Repository**
```bash
   git clone https://github.com/garlic-signage/garlic-hub.git
   cd garlic-hub
```
## Install ddev

Find a docker provider for your Operating System, open a terminal in your IDE. If you like customize the ./ddev/config.yaml file to your needs, but it is not required.

```bash
ddev start
```

### Install PHP Dependencies
Run Composer to install required PHP packages:

```php
composer install
```
### Set Up Environment Variables
Copy the example environment file and update it as needed:

```bash
cp .env.dist .env
```
### Installer
Create directories, configure the database and more:
```bash
bash dev-install.sh
```
The login will be created with the following credentials:
- **login**: admin
- **password**: thymian
