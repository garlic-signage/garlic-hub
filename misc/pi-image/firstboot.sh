#!/bin/bash
# First start of a Pi with the garlic-hub image: creates what must be unique for every installation
# and therefore is not in the image: APP_SECRET, the crypto keys and the database.
# Runs again on the next start if it failed, everything in it may run twice.
set -euo pipefail

APP=/var/www/garlic-hub
DONE=/var/lib/garlic-hub/firstboot.done

cd "$APP"
umask 027

# media.php signs its delivery urls with it, so an empty value counts as missing
if ! grep -qE '^APP_SECRET=[[:space:]]*[^[:space:]#]' .env; then
    echo "Create random APP_SECRET..."
    sed -i '/^APP_SECRET=/d' .env
    echo "APP_SECRET=$(openssl rand -hex 16)" >> .env
fi

if [[ -f var/keys/private.key && -f var/keys/public.key && -f var/keys/encryption.key ]]; then
    echo "Keys already exist. Skipping generation."
else
    echo "Create crypto keys..."
    openssl genpkey -algorithm RSA -out var/keys/private.key -pkeyopt rsa_keygen_bits:2048
    openssl rsa -pubout -in var/keys/private.key -out var/keys/public.key
    php vendor/bin/generate-defuse-key > var/keys/encryption.key
fi
chown -R www-data:www-data var

runuser -u www-data -- php bin/console.php db:migrate

mkdir -p "$(dirname "$DONE")"
touch "$DONE"
