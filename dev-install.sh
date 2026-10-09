#!/bin/bash

mkdir -p var/cache
mkdir -p var/logs
mkdir -p var/weblogs
mkdir -p var/sessions
mkdir -p var/keys
mkdir -p var/storage/mediapool/thumbs
mkdir -p var/storage/mediapool/originals
mkdir -p var/storage/templates/thumbs
mkdir -p var/storage/templates/originals

if [[ -f var/keys/private.key && -f var/keys/public.key && -f var/keys/encryption.key ]]; then
    echo "Keys already exist. Skipping generation."
else
    echo "Create crypto keys..."
    php vendor/bin/generate-defuse-key > var/keys/encryption.key
    openssl genpkey -algorithm RSA -out var/keys/private.key -pkeyopt rsa_keygen_bits:2048
    openssl rsa -pubout -in var/keys/private.key -out var/keys/public.key
    echo "Keys successfully created!"
fi

# start db migration
php bin/console.php db:migrate 2>&1
