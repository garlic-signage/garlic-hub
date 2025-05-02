#!/bin/bash

# create .env secret only when not exists


chown -R www-data:www-data /var/www/public/var /var/www/var
chmod -R 775 /var/www/public/var /var/www/var

mkdir -p \
  /var/www/var \
  /var/www/var/cache \
  /var/www/var/logs \
  /var/www/var/weblogs \
  /var/www/var/keys \
  /var/www/public/var/mediapool \
  /var/www/public/var/mediapool/thumbs \
  /var/www/public/var/mediapool/originals

if ! grep -q '^APP_SECRET=' /var/www/.env; then
    echo "Appending random APP_SECRET..."
    echo "APP_SECRET=$(openssl rand -hex 16)" >> /var/www/.env
fi

if [[ -f var/keys/private.key && -f var/keys/public.key && -f var/keys/encryption.key ]]; then
    echo "Keys already exist. Skipping generation."
else
    echo "Create crypto keys..."
    openssl genpkey -algorithm RSA -out var/keys/private.key -pkeyopt rsa_keygen_bits:2048
    openssl rsa -pubout -in var/keys/private.key -out var/keys/public.key
    head -c 32 /dev/urandom | base64 > var/keys/encryption.key
    echo "Keys successfully created!"
fi
# Installation when db missing
if [ ! -f /var/www/var/garlic-hub.sqlite ]; then
    sqlite3 /var/www/var/garlic-hub.sqlite < migrations/edge/001_init.up.sql
	#php bin/console.php db:migrate 2>&1
else
    echo "Install already done."
fi

chown -R www-data:www-data /var/www/public/var /var/www/var
chmod -R 775 /var/www/public/var /var/www/var
umask 002


# Apache starten
exec apache2-foreground
