#!/bin/bash
# Runs on every start of a Pi with the garlic-hub image, before PHP-FPM and Apache.
#
# 1. Sets the number of PHP workers, the memory limit of PHP and the limits of ImageMagick by the memory
#    of this Pi: the card may move to another Pi.
# 2. On the first start: creates what must be unique for every installation and therefore is not in the
#    image: APP_SECRET, the crypto keys and the database. Runs again on the next start if it failed,
#    everything in it may run twice.
set -euo pipefail

APP=/var/www/garlic-hub
DONE=/var/lib/garlic-hub/firstboot.done
POOL=/etc/php/8.4/fpm/pool.d/zz-garlic-hub.conf
INI=/etc/php/8.4/fpm/conf.d/zz-garlic-hub-memory.ini

mem_mb=$(( $(awk '/^MemTotal:/ {print $2}' /proc/meminfo) / 1024 ))

# MemTotal is a bit below the size of the board: a Pi with 1 GB shows about 900 MB
if   [ "$mem_mb" -lt 1500 ]; then workers=6;  php_mem=256M; im_mem=128MiB; im_map=256MiB
elif [ "$mem_mb" -lt 3000 ]; then workers=10; php_mem=512M; im_mem=256MiB; im_map=512MiB
else                              workers=16; php_mem=512M; im_mem=512MiB; im_map=1GiB
fi

echo "Memory ${mem_mb} MB: ${workers} PHP workers, memory_limit ${php_mem}, ImageMagick ${im_mem}/${im_map}"

# a pool of a fixed size: more requests wait in the backlog instead of filling the memory.
# The same pool name as www.conf, the later file overrides its values.
cat > "$POOL" <<EOF
; written by /usr/local/lib/garlic-hub/boot.sh on every start
[www]
pm = static
pm.max_children = ${workers}
pm.max_requests = 500
listen.backlog = 511
request_terminate_timeout = 1200
EOF

printf '; written by /usr/local/lib/garlic-hub/boot.sh on every start\nmemory_limit = %s\n' "$php_mem" > "$INI"

for policy in /etc/ImageMagick-*/policy.xml; do
    [ -f "$policy" ] || continue
    sed -i -E \
        -e "s#(<policy domain=\"resource\" name=\"memory\" value=\")[^\"]*#\1${im_mem}#" \
        -e "s#(<policy domain=\"resource\" name=\"map\" value=\")[^\"]*#\1${im_map}#" \
        "$policy"
done

[ -f "$DONE" ] && exit 0

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
