#!/bin/bash

# create .env secret only when not exists

umask 002
chown -R www-data:www-data /var/www/public/var /var/www/var
chmod -R 775 /var/www/public/var /var/www/var

# move mediapool/templates from public/var to var/storage (must run before mkdir below)
/var/www/bin/migrate-storage.sh /var/www || exit 1

mkdir -p \
  /var/www/var \
  /var/www/var/cache \
  /var/www/var/logs \
  /var/www/var/weblogs \
  /var/www/var/devicelogs \
  /var/www/var/keys \
  /var/www/var/sessions \
  /var/www/var/storage/mediapool/thumbs \
  /var/www/var/storage/mediapool/originals \
  /var/www/var/storage/templates/thumbs \
  /var/www/var/storage/templates/originals \
  /var/www/var/storage/playlists/items/thumbs \
  /var/www/var/storage/playlists/items/originals

# media.php signs its delivery urls with it, so an empty value (as in .env.dist) counts as missing
if ! grep -qE '^APP_SECRET=[[:space:]]*[^[:space:]#]' /var/www/.env; then
    echo "Appending random APP_SECRET..."
    sed -i '/^APP_SECRET=/d' /var/www/.env
    echo "APP_SECRET=$(openssl rand -hex 16)" >> /var/www/.env
fi

if ! grep -q '^APP_CONTAINER=' /var/www/.env; then
    echo "Setting CONTAINER environment variable..."
    echo "APP_CONTAINER=docker" >> /var/www/.env
fi

if [[ -f var/keys/private.key && -f var/keys/public.key && -f var/keys/encryption.key ]]; then
    echo "Keys already exist. Skipping generation."
else
    echo "Create crypto keys..."
    openssl genpkey -algorithm RSA -out var/keys/private.key -pkeyopt rsa_keygen_bits:2048
    openssl rsa -pubout -in var/keys/private.key -out var/keys/public.key
    php vendor/bin/generate-defuse-key > var/keys/encryption.key
    echo "Keys successfully created!"
fi

php bin/console.php db:migrate

chown -R www-data:www-data /var/www/public/var /var/www/var
chmod -R 775 /var/www/public/var /var/www/var


# Apache starten
exec apache2-foreground
