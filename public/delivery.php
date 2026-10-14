<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program. If not, see <https://www.gnu.org/licenses/>.
*/

/**
 * Standalone delivery of mediapool ORIGINAL files for players, from
 * var/storage/mediapool/originals. Deliberately independent of the CMS bootstrap
 * (no container, session or DB), same as media.php - but unlike media.php there is no
 * expiry: players hold these links for as long as the exported SMIL playlist is in use,
 * not just for one browser session, and checksum already is the file's content hash,
 * so the link is correct to cache forever (same checksum = same bytes, always).
 *
 * GET parameters: c (checksum), ext
 *
 * The only check right now is isAuthorizedPlayer() below: the request must carry a
 * player-shaped UUID in its User-Agent/X-Signage-Agent header (same formats as
 * UserAgentHandler::parseUserAgent()). This is a cheap sanity filter only - it does not
 * verify the UUID against anything, so it is NOT real access control, just a place to
 * plug a real licence/revocation check into later without touching callers.
 */

$systemDir = dirname(__DIR__);

require $systemDir . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($systemDir)->safeLoad();

const MEDIA_MIME = [
	// Images
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'avif' => 'image/avif',
	'png'  => 'image/png',
	'gif'  => 'image/gif',
	'webp' => 'image/webp',
	'svg'  => 'image/svg+xml',
	'bmp'  => 'image/bmp',
	'tif'  => 'image/tiff',
	// Audios
	'mp3'  => 'audio/mpeg',
	'ogg'  => 'audio/ogg',
	'opus' => 'audio/opus',
	'wav'  => 'audio/wav',
	'mp4'  => 'video/mp4',
	'avi'  => 'video/x-msvideo',
	'mkv'  => 'video/x-matroska',
	'webm' => 'video/webm',
	'mov'  => 'video/quicktime',
	'mpg'  => 'video/mpeg',
	// PDF
	'pdf'  => 'application/pdf',
	// Widgets and miscellaneous: delivered as download
	'wgt'   => 'application/octet-stream',
	'zip'   => 'application/zip',
	'json'  => 'application/json',
	'xml'   => 'application/xml',
	'rss'   => 'application/rss+xml',
	'atom'  => 'application/atom+xml',
	'apk'   => 'application/vnd.android.package-archive',
	'txt'   => 'text/plain',
	'csv'   => 'text/csv',
	'bin'   => 'application/octet-stream',
];
const MEDIA_DOWNLOAD = ['wgt', 'zip', 'json', 'xml', 'rss', 'atom', 'apk', 'txt', 'csv', 'bin'];

function deliveryFail(int $status): never
{
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
	exit;
}

/**
 * Cheap sanity filter, NOT real authorization: does the request claim to come from a
 * player with a UUID at all? A real check (licence/revocation lookup by this UUID)
 * is the intended extension point here later - deliberately left as a single, easily
 * replaceable function. Kept as a plain regex (same "UUID:..." shape accepted by
 * UserAgentHandler::parseUserAgent()) instead of reusing that class, because it drags
 * in Config/PlayerDetector - this script stays bootstrap-free like media.php.
 */
function isAuthorizedPlayer(array $server): bool
{
	$agent = $server['HTTP_X_SIGNAGE_AGENT'] ?? ($server['HTTP_USER_AGENT'] ?? '');
	if (!is_string($agent) || $agent === '')
		return false;

	return (bool) preg_match('/UUID:([^;)]+)/', $agent);
}

if (!isAuthorizedPlayer($_SERVER))
	deliveryFail(403);

$checksum = $_GET['c']   ?? '';
$ext      = $_GET['ext'] ?? '';

if (!is_string($checksum) || !is_string($ext)
	|| !preg_match('/^[a-f0-9]{32,64}$/', $checksum) || !isset(MEDIA_MIME[$ext]))
	deliveryFail(400);

// Must stay in sync with [originals] in config/settings/config_mediapool.ini.
$baseDir = realpath($systemDir . '/var/storage/mediapool/originals');
if ($baseDir === false)
	deliveryFail(404);

$real = realpath($baseDir . '/' . $checksum . '.' . $ext);
if ($real === false || !str_starts_with($real, $baseDir . '/') || !is_file($real))
	deliveryFail(404);

$size = filesize($real);
// checksum already is the content hash, so it alone is a correct, stable ETag.
$etag = '"' . $checksum . '"';

header('Content-Type: ' . MEDIA_MIME[$ext]);
if (in_array($ext, MEDIA_DOWNLOAD, true))
	header('Content-Disposition: attachment; filename="' . $checksum . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Accept-Ranges: bytes');
header('ETag: ' . $etag);
// Safe to cache forever: same checksum will never resolve to different bytes.
header('Cache-Control: public, max-age=31536000, immutable');

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag)
{
	http_response_code(304);
	exit;
}

$start = 0;
$end   = $size - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '')
{
	if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) || ($m[1] === '' && $m[2] === ''))
	{
		header('Content-Range: bytes */' . $size);
		deliveryFail(416);
	}
	if ($m[1] === '')                       // suffix range: last N bytes
	{
		$start = max($size - (int) $m[2], 0);
	}
	else
	{
		$start = (int) $m[1];
		if ($m[2] !== '')
			$end = min((int) $m[2], $size - 1);
	}
	if ($start > $end || $start >= $size)
	{
		header('Content-Range: bytes */' . $size);
		deliveryFail(416);
	}
	http_response_code(206);
	header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

if ($_SERVER['REQUEST_METHOD'] === 'HEAD')
	exit;

$fp = fopen($real, 'rb');
if ($fp === false)
	deliveryFail(500);

set_time_limit(0);
fseek($fp, $start);
while ($length > 0 && !feof($fp) && !connection_aborted())
{
	$chunk = fread($fp, min(8192 * 8, $length));
	if ($chunk === false)
		break;
	echo $chunk;
	$length -= strlen($chunk);
	flush();
}
fclose($fp);
