#!/bin/bash
# Updates garlic-hub on a Pi with the garlic-hub image to the latest release. Installed as /usr/local/sbin/garlic-hub-update.
#
# Layout: releases/<version> holds the code, shared/ the data (var/, .env), current points to the running release.
# The new release is unpacked beside the running one and gets the shared data as links. The database is saved and
# migrated, only then current switches over. If something fails before, the old release keeps running.
# Rollback: point current back to the old release and restore the backup in shared/var/backups.
set -euo pipefail

BASE=/var/www/garlic-hub
SHARED=$BASE/shared
URL="${GARLIC_HUB_RELEASE_URL:-https://github.com/garlic-signage/garlic-hub/releases/latest/download}"
KEEP=3

[ "$(id -u)" = 0 ] || { echo "run as root: sudo garlic-hub-update" >&2; exit 1; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "== Download"
curl -fsSL -o "$TMP/garlic-hub.tar.gz"        "$URL/garlic-hub.tar.gz"
curl -fsSL -o "$TMP/garlic-hub.tar.gz.sha256" "$URL/garlic-hub.tar.gz.sha256"
(cd "$TMP" && sha256sum -c --quiet garlic-hub.tar.gz.sha256)

NEW="$(tar -xzOf "$TMP/garlic-hub.tar.gz" ./VERSION | head -n1 | tr -d '[:space:]')"
OLD="$(head -n1 "$BASE/current/VERSION" | tr -d '[:space:]')"
[ -n "$NEW" ] || { echo "no VERSION in the archive" >&2; exit 1; }
if [ "$NEW" = "$OLD" ]; then
    echo "garlic-hub $OLD is the latest release"
    exit 0
fi
echo "garlic-hub $OLD -> $NEW"

echo "== Unpack"
REL="$BASE/releases/$NEW"
rm -rf "$REL" # left over from a failed run
mkdir -p "$REL"
tar -xzf "$TMP/garlic-hub.tar.gz" -C "$REL" --no-same-owner
rm -rf "$REL/var"
ln -s "$SHARED/var"  "$REL/var"
ln -s "$SHARED/.env" "$REL/.env"

echo "== Database: backup, migration"
DB="$SHARED/var/garlic-hub.sqlite"
if [ -f "$DB" ]; then
    mkdir -p "$SHARED/var/backups"
    chown www-data:www-data "$SHARED/var/backups"
    runuser -u www-data -- sqlite3 "$DB" ".backup $SHARED/var/backups/before-$NEW.sqlite"
fi
(cd "$REL" && runuser -u www-data -- php bin/console.php db:migrate)

echo "== Switch"
ln -sfn "$REL" "$BASE/current.new"
mv -T "$BASE/current.new" "$BASE/current"
systemctl reload php8.4-fpm

echo "== Clean up: keep the latest $KEEP releases"
CURRENT="$(readlink -f "$BASE/current")"
ls -1dt "$BASE"/releases/* | tail -n +$((KEEP + 1)) | while read -r dir; do
    [ "$(readlink -f "$dir")" = "$CURRENT" ] || rm -rf "$dir"
done

echo "garlic-hub $NEW is running"
