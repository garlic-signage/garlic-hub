# Raspberry Pi Image

Every release has a ready image for the Raspberry Pi: Raspberry Pi OS Lite (64-bit) with garlic-hub installed bare
metal, CMS only. It runs Apache with PHP-FPM and SQLite, the Pi plays nothing itself.

The image is written with the [Raspberry Pi Imager](https://www.raspberrypi.com/software/). The Imager sets the
user, the WLAN and SSH, but only for images of a content repository, not for an image chosen as a local file.
Therefore every release has the repository `garlic-hub-pi.json` beside the image. Its address stays the same and
always points to the latest release:

```
https://github.com/garlic-signage/garlic-hub/releases/latest/download/garlic-hub-pi.json
```

## Requirements

- Raspberry Pi 4 (or 400) with 1 GB or more, Raspberry Pi 5. A Raspberry Pi 3 runs it, but its 100 Mbit network is
  too slow to deliver the media to many players at once.
- A microSD card with 32 GB or more, class A2 is noticeably faster with the database.
- Raspberry Pi Imager 2.0 or newer.

## Write the card

1. Start the Imager, open the **App Options** and set the **Content Repository** to the custom URL above.
   The Imager now lists only garlic-hub. To write another OS later, set the repository back to the default.
   From the command line it works without the setting: `rpi-imager --repo <URL>`.
2. Choose your Raspberry Pi model.
3. Choose **garlic-hub** as the operating system and the card as storage.
4. Customise the system:
   - hostname, e.g. `garlic-hub`
   - user and password: there is no default user, without one you cannot log in on the Pi
   - WLAN with name, password and country, if the Pi is not connected by cable
   - time zone and keyboard
   - SSH on, with password or public key
5. Write the card, put it into the Pi and switch it on.

## First start

The first start takes a few minutes longer: cloud-init applies the settings of the Imager, then garlic-hub
creates its secret, the crypto keys and the database. Nothing of this is in the image, so every Pi gets its own.

Then open `http://<hostname>.local`, e.g. `http://garlic-hub.local`, or the IP address of the Pi. The first request
leads to `/create-initial`, where you create the admin user.

The image serves HTTP only. For HTTPS put a reverse proxy in front of it or set up a certificate in Apache.

## Memory

On every start the Pi sets the number of PHP workers and the memory limits of PHP and ImageMagick by its memory,
so the card also works after a move to another Pi:

| Memory    | PHP workers | PHP `memory_limit` | ImageMagick memory / map |
|-----------|-------------|--------------------|--------------------------|
| 1 GB      | 6           | 256M               | 128MiB / 256MiB          |
| 2 GB      | 10          | 512M               | 256MiB / 512MiB          |
| 4 GB+     | 16          | 512M               | 512MiB / 1GiB            |

More requests than workers wait in a queue instead of filling the memory. Downloads of the players do not
occupy a PHP worker: `delivery.php` hands them to Apache with X-Sendfile.

## Build the image yourself

The workflow `.github/workflows/pi-image.yml` builds the image for every published release and attaches it with
`garlic-hub-pi.json` to the release. By hand on an arm64 Linux host:

```bash
sudo misc/pi-image/build.sh <git-tag> [output-dir]
```

`DOWNLOAD_URL` sets the address of the image in `garlic-hub-pi.json`, default is the GitHub release of the tag.
