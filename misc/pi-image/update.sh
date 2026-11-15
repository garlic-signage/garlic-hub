#!/bin/bash
# Updates garlic-hub on a Pi with the garlic-hub image to the latest release. Installed as /usr/local/sbin/garlic-hub-update.
# Started by hand (sudo garlic-hub-update) or by the button on the page /system/update: PHP writes
# shared/var/update/requested, garlic-hub-update.path sees it and starts garlic-hub-update.service.
# At night garlic-hub-update-auto.timer starts it with --auto: then it only runs when the switch
# "Automatic updates" is on in the system settings (Update.auto in shared/var/settings/config_system.ini).
#
# Layout: releases/<version> holds the code, shared/ the data (var/, .env), current points to the running release.
# The new release is unpacked beside the running one and gets the shared data as links. The database is saved and
# migrated, only then current switches over. If something fails before, the old release keeps running.
# Rollback: point current back to the old release and restore the backup in shared/var/backups.
#
# The progress is in shared/var/update/status.json for the page. The directory belongs to www-data: the script
# writes there only by a new temporary file and a rename, never through a name PHP could have made a link.
set -euo pipefail

BASE=/var/www/garlic-hub
SHARED=$BASE/shared
UPDATE_DIR=$SHARED/var/update
URL="${GARLIC_HUB_RELEASE_URL:-https://github.com/garlic-signage/garlic-hub/releases/latest/download}"
KEEP=3
AUTO=0
[ "${1:-}" = "--auto" ] && AUTO=1

[ "$(id -u)" = 0 ] || { echo "run as root: sudo garlic-hub-update" >&2; exit 1; }

# the switch of the settings page, off without the file: PHP writes only the changed values
auto_enabled() {
    local ini="$SHARED/var/settings/config_system.ini"
    [ -f "$ini" ] || return 1
    awk '/^\[/ { section = $0; next }
         section == "[Update]" && $0 ~ /^[ \t]*auto[ \t]*=/ { sub(/^[^=]*=[ \t]*/, ""); gsub(/"/, ""); sub(/[ \t;].*$/, ""); value = $0 }
         END { exit value == "1" ? 0 : 1 }' "$ini"
}
if [ "$AUTO" = 1 ] && ! auto_enabled; then
    exit 0
fi

exec 9> /run/garlic-hub-update.lock
flock -n 9 || { echo "an update is already running" >&2; exit 1; }

# the request of the page is taken, the path unit must not start the service again
[ -d "$UPDATE_DIR" ] && rm -f "$UPDATE_DIR/requested"

STEP=download
OLD="$(head -n1 "$BASE/current/VERSION" | tr -cd 'A-Za-z0-9.+_-')"
NEW=""

status() {
    [ -d "$UPDATE_DIR" ] || return 0
    local tmp
    tmp="$(mktemp "$UPDATE_DIR/.status.XXXXXX")"
    printf '{"state":"%s","step":"%s","from":"%s","to":"%s","time":"%s"}\n' \
        "$1" "$STEP" "$OLD" "$NEW" "$(date -u +%FT%TZ)" > "$tmp"
    chmod 644 "$tmp"
    mv -fT "$tmp" "$UPDATE_DIR/status.json"
}
trap 'status failed' ERR

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

status running
echo "== Download"
curl -fsSL -o "$TMP/garlic-hub.tar.gz"        "$URL/garlic-hub.tar.gz"
curl -fsSL -o "$TMP/garlic-hub.tar.gz.sha256" "$URL/garlic-hub.tar.gz.sha256"
(cd "$TMP" && sha256sum -c --quiet garlic-hub.tar.gz.sha256)

NEW="$(tar -xzOf "$TMP/garlic-hub.tar.gz" ./VERSION | head -n1 | tr -cd 'A-Za-z0-9.+_-')"
[ -n "$NEW" ] || { echo "no VERSION in the archive" >&2; false; }
if [ "$NEW" = "$OLD" ]; then
    STEP=""
    status current
    echo "garlic-hub $OLD is the latest release"
    exit 0
fi
echo "garlic-hub $OLD -> $NEW"

STEP=unpack
status running
echo "== Unpack"
REL="$BASE/releases/$NEW"
# never the running one, e.g. a directory whose name is not the VERSION in it
[ "$(readlink -f "$REL")" != "$(readlink -f "$BASE/current")" ] || { echo "$REL is the running release" >&2; false; }
rm -rf "$REL" # left over from a failed run
mkdir -p "$REL"
tar -xzf "$TMP/garlic-hub.tar.gz" -C "$REL" --no-same-owner
rm -rf "$REL/var"
ln -s "$SHARED/var"  "$REL/var"
ln -s "$SHARED/.env" "$REL/.env"

STEP=migrate
status running
echo "== Database: backup, migration"
DB="$SHARED/var/garlic-hub.sqlite"
if [ -f "$DB" ]; then
    mkdir -p "$SHARED/var/backups"
    chown www-data:www-data "$SHARED/var/backups"
    runuser -u www-data -- sqlite3 "$DB" ".backup $SHARED/var/backups/before-$NEW.sqlite"
fi
(cd "$REL" && runuser -u www-data -- php bin/console.php db:migrate)

STEP=switch
status running
echo "== Switch"
ln -sfn "$REL" "$BASE/current.new"
mv -T "$BASE/current.new" "$BASE/current"
systemctl reload php8.4-fpm

STEP=cleanup
echo "== Clean up: keep the latest $KEEP releases"
CURRENT="$(readlink -f "$BASE/current")"
ls -1dt "$BASE"/releases/* | tail -n +$((KEEP + 1)) | while read -r dir; do
    [ "$(readlink -f "$dir")" = "$CURRENT" ] || rm -rf "$dir"
done

STEP=""
status done
echo "garlic-hub $NEW is running"
