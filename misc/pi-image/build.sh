#!/bin/bash
# Builds a Raspberry Pi OS Lite (arm64) image with garlic-hub installed bare metal, CMS only:
# Apache (mpm_event) with PHP-FPM 8.4, SQLite, as in the Raspberry Pi tutorial on garlic-signage.com.
#
# Usage: sudo misc/pi-image/build.sh <git-tag> [output-dir]
#
# Runs as root on an arm64 Linux host (the arm runner of GitHub), on x86 it needs qemu-user-static
# with binfmt. It takes the official image, grows it, installs garlic-hub of the tag in a chroot and
# packs it again. Secrets, keys and the database are NOT in the image, every Pi creates its own on
# the first start (boot.sh).
#
# RASPIOS_URL pins another base image, default is the latest Raspberry Pi OS Lite.
#
# Besides the image it writes garlic-hub-pi.json, a content repository for the Raspberry Pi Imager: with it the
# user sets the user, WLAN and SSH in the Imager, which it does not offer for an image chosen as a local file.
# DOWNLOAD_URL is the address of the image without the file name, default the GitHub release of the tag.
set -euo pipefail

REF="${1:?usage: build.sh <git-tag> [output-dir]}"
OUT="${2:-$PWD/pi-image-out}"
BASE_URL="${RASPIOS_URL:-https://downloads.raspberrypi.com/raspios_lite_arm64_latest}"
GROW_MB="${GROW_MB:-1536}"
DOWNLOAD_URL="${DOWNLOAD_URL:-https://github.com/garlic-signage/garlic-hub/releases/download/$REF}"
APP=/var/www/garlic-hub

HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
# the checkout belongs to another user than root
git() { command git -c safe.directory="$REPO" "$@"; }
NAME="garlic-hub-${REF//\//-}-raspios-lite-arm64"

[ "$(id -u)" = 0 ] || { echo "run as root" >&2; exit 1; }
git -C "$REPO" rev-parse --verify --quiet "$REF^{commit}" > /dev/null || { echo "unknown ref $REF" >&2; exit 1; }

WORK="$(mktemp -d)"
ROOT="$WORK/root"
IMG="$WORK/$NAME.img"
LOOP=""

cleanup() {
    set +e
    if [ -d "$ROOT" ] && findmnt -R "$ROOT" > /dev/null; then umount -R "$ROOT"; fi
    [ -n "$LOOP" ] && losetup -d "$LOOP"
    # never delete through a mount that is still there (/dev is bound into the chroot)
    if [ -d "$ROOT" ] && findmnt -R "$ROOT" > /dev/null; then
        echo "WARNING: $ROOT is still mounted, $WORK is kept" >&2
    else
        rm -rf "$WORK"
    fi
}
trap cleanup EXIT

in_root() {
    chroot "$ROOT" /usr/bin/env DEBIAN_FRONTEND=noninteractive LC_ALL=C.UTF-8 "$@"
}

echo "== Base image"
IMG_URL="$(curl -fsSIL -o /dev/null -w '%{url_effective}' "$BASE_URL")"
echo "$IMG_URL"
curl -fL --retry 3 -o "$WORK/base.img.xz" "$IMG_URL"
echo "$(curl -fsSL "$IMG_URL.sha256" | cut -d' ' -f1)  $WORK/base.img.xz" | sha256sum -c -
xz -dc "$WORK/base.img.xz" > "$IMG"
rm "$WORK/base.img.xz"

echo "== Grow the root partition by ${GROW_MB} MB"
truncate -s "+${GROW_MB}M" "$IMG"
parted -s "$IMG" resizepart 2 100%
LOOP="$(losetup -fP --show "$IMG")"
e2fsck -fy "${LOOP}p2" || [ $? -le 1 ]
resize2fs "${LOOP}p2"

echo "== Mount"
mkdir -p "$ROOT"
mount "${LOOP}p2" "$ROOT"
mount "${LOOP}p1" "$ROOT/boot/firmware"
mount -t proc proc "$ROOT/proc"
mount -t sysfs sys "$ROOT/sys"
mount --bind /dev "$ROOT/dev"
mount --bind /dev/pts "$ROOT/dev/pts"

# the chroot needs DNS, no services may start in it, and the identity of the base image stays as it is
if [ -e "$ROOT/etc/resolv.conf" ] || [ -L "$ROOT/etc/resolv.conf" ]; then mv "$ROOT/etc/resolv.conf" "$WORK/resolv.conf.orig"; fi
cp -L /etc/resolv.conf "$ROOT/etc/resolv.conf"
if [ -e "$ROOT/etc/machine-id" ]; then cp -a "$ROOT/etc/machine-id" "$WORK/machine-id.orig"; fi
printf '#!/bin/sh\nexit 101\n' > "$ROOT/usr/sbin/policy-rc.d"
chmod 755 "$ROOT/usr/sbin/policy-rc.d"

echo "== Packages"
in_root apt-get update -q
in_root apt-get -y -q full-upgrade
in_root apt-get -y -q install --no-install-recommends \
    apache2 libapache2-mod-xsendfile \
    php8.4-fpm php8.4-cli php8.4-curl php8.4-intl php8.4-mbstring php8.4-xml php8.4-zip \
    php8.4-sqlite3 php8.4-opcache php-imagick \
    composer unzip sqlite3 ffmpeg ghostscript cron openssl

echo "== garlic-hub $REF"
mkdir -p "$ROOT$APP"
git -C "$REPO" archive "$REF" | tar -x -C "$ROOT$APP"
# not needed to run it, as in .dockerignore
rm -rf "$ROOT$APP"/{.ddev,.github,tests,misc}
in_root sh -c "cd $APP && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --classmap-authoritative"
rm -rf "$ROOT/root/.cache/composer" "$ROOT/root/.config/composer"

for dir in cache tmp logs weblogs devicelogs keys settings sessions \
    storage/mediapool/thumbs storage/mediapool/originals \
    storage/templates/thumbs storage/templates/originals \
    storage/playlists/items/thumbs storage/playlists/items/originals; do
    mkdir -p "$ROOT$APP/var/$dir"
done

# without APP_SECRET: boot.sh adds a random one
sed "s#^DB_MASTER_PATH=.*#DB_MASTER_PATH=$APP/var/garlic-hub.sqlite#" "$ROOT$APP/dockerapp-configs/env.core" > "$ROOT$APP/.env"

# the code belongs to root, PHP writes only below var/
chown -R root:root "$ROOT$APP"
in_root chown root:www-data "$APP/.env"
chmod 640 "$ROOT$APP/.env"
in_root chown -R www-data:www-data "$APP/var"
chmod -R u=rwX,g=rwX,o= "$ROOT$APP/var"

echo "== Apache, PHP, ImageMagick"
# the configuration of the Docker image, with the path of this installation
sed "s#/var/www#$APP#g" "$ROOT$APP/dockerapp-configs/http.conf" > "$ROOT/etc/apache2/sites-available/garlic-hub.conf"
# not mod_php with prefork as in the Docker image: mpm_event with PHP-FPM, so it fits on a Pi with 1 GB
in_root a2dismod -q mpm_prefork || true
in_root a2enmod -q mpm_event proxy_fcgi setenvif rewrite xsendfile headers
in_root a2enconf -q php8.4-fpm
install -m 644 "$HERE/apache-tuning.conf" "$ROOT/etc/apache2/conf-available/garlic-hub-tuning.conf"
in_root a2enconf -q garlic-hub-tuning
in_root a2dissite -q 000-default
in_root a2ensite -q garlic-hub

# memory_limit sets boot.sh by the memory of the Pi. The uploads go to the card, not to /tmp:
# as a tmpfs it is in the memory, and a video of 1 GB does not fit in a Pi with 1 GB.
{
    sed '/^memory_limit/d' "$ROOT$APP/dockerapp-configs/php.ini-custom"
    printf '\nupload_tmp_dir = %s\nsys_temp_dir = %s\n' "$APP/var/tmp" "$APP/var/tmp"
} > "$ROOT/etc/php/8.4/fpm/conf.d/99-garlic-hub.ini"

found=0
for dir in "$ROOT"/etc/ImageMagick-*; do
    [ -d "$dir" ] || continue
    cp "$ROOT$APP/dockerapp-configs/policy.xml" "$dir/policy.xml"
    found=1
done
[ "$found" = 1 ] || { echo "no ImageMagick configuration found" >&2; exit 1; }

# new files of PHP stay writable for the group, as umask 002 in the Docker image
mkdir -p "$ROOT/etc/systemd/system/php8.4-fpm.service.d"
printf '[Service]\nUMask=0002\n' > "$ROOT/etc/systemd/system/php8.4-fpm.service.d/garlic-hub.conf"

# the scheduler: every 5 minutes, it ends at once while it is not enabled on the settings page
echo "*/5 * * * * www-data cd $APP && /usr/bin/php bin/console.php schedule:run >> $APP/var/logs/scheduler.log 2>&1" > "$ROOT/etc/cron.d/garlic-hub"
chmod 644 "$ROOT/etc/cron.d/garlic-hub"

echo "== Start: tuning on every boot, secret, keys and database on the first one"
install -D -m 755 "$HERE/boot.sh" "$ROOT/usr/local/lib/garlic-hub/boot.sh"
install -m 644 "$HERE/garlic-hub-boot.service" "$ROOT/etc/systemd/system/garlic-hub-boot.service"
in_root systemctl enable garlic-hub-boot.service php8.4-fpm.service apache2.service cron.service

# the Imager writes its settings for cloud-init (Trixie) or for a firstrun script (Bookworm and older)
if [ -x "$ROOT/usr/bin/cloud-init" ]; then INIT_FORMAT=cloudinit-rpi; else INIT_FORMAT=systemd; fi

echo "== Clean up"
in_root apt-get clean
rm -rf "$ROOT"/var/lib/apt/lists/*
# an upgrade of openssh-server may have created host keys, they must not be the same on every Pi
rm -f "$ROOT"/etc/ssh/ssh_host_*
rm -f "$ROOT/usr/sbin/policy-rc.d"
if [ -e "$WORK/machine-id.orig" ]; then cp -a "$WORK/machine-id.orig" "$ROOT/etc/machine-id"; else rm -f "$ROOT/etc/machine-id"; fi
rm -f "$ROOT/etc/resolv.conf"
if [ -e "$WORK/resolv.conf.orig" ] || [ -L "$WORK/resolv.conf.orig" ]; then mv "$WORK/resolv.conf.orig" "$ROOT/etc/resolv.conf"; fi

umount "$ROOT/dev/pts" "$ROOT/dev" "$ROOT/sys" "$ROOT/proc"
# the free blocks become holes in the file, xz packs them to nearly nothing
fstrim "$ROOT/boot/firmware" || true
fstrim "$ROOT" || true
umount "$ROOT/boot/firmware" "$ROOT"
losetup -d "$LOOP"
LOOP=""

echo "== Pack"
mkdir -p "$OUT"
EXTRACT_SIZE="$(stat -c %s "$IMG")"
EXTRACT_SHA256="$(sha256sum "$IMG" | cut -d' ' -f1)"
xz -T0 -6 -c "$IMG" > "$OUT/$NAME.img.xz"
(cd "$OUT" && sha256sum "$NAME.img.xz" > "$NAME.img.xz.sha256")

# the same name in every release: releases/latest/download/garlic-hub-pi.json stays the address for the Imager
cat > "$OUT/garlic-hub-pi.json" <<EOF
{
  "imager": {
    "devices": [
      { "name": "Raspberry Pi 5", "tags": ["pi5-64bit"], "description": "Raspberry Pi 5, 500 / 500+, and Compute Module 5", "matching_type": "exclusive" },
      { "name": "Raspberry Pi 4", "tags": ["pi4-64bit"], "description": "Raspberry Pi 4 Model B, 400, and Compute Module 4 / 4S", "matching_type": "inclusive", "default": true },
      { "name": "Raspberry Pi 3", "tags": ["pi3-64bit"], "description": "Raspberry Pi 3 Model A+ / B / B+ and Compute Module 3 / 3+", "matching_type": "inclusive" }
    ]
  },
  "os_list": [
    {
      "name": "garlic-hub ${REF##*/}",
      "description": "Raspberry Pi OS Lite (64-bit) with the garlic-hub CMS, Apache, PHP and SQLite",
      "url": "$DOWNLOAD_URL/$NAME.img.xz",
      "extract_size": $EXTRACT_SIZE,
      "extract_sha256": "$EXTRACT_SHA256",
      "image_download_size": $(stat -c %s "$OUT/$NAME.img.xz"),
      "image_download_sha256": "$(cut -d' ' -f1 "$OUT/$NAME.img.xz.sha256")",
      "release_date": "$(date -u +%F)",
      "init_format": "$INIT_FORMAT",
      "devices": ["pi5-64bit", "pi4-64bit", "pi3-64bit"]
    }
  ]
}
EOF
ls -lh "$OUT"
