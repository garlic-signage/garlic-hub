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
 * Called as delivery.php/<checksum>.<ext> for mediapool files and delivery.php/tpl/<item_id>.<ext>
 * for rendered template items (PATH_INFO). The file name has to be part of the
 * URL path: garlic-player derives the extension of its cache file from the path, a query
 * string like delivery.php?c=..&ext=png would be stored as *.php.
 *
 * The only check right now is isAuthorizedPlayer() below: the request must carry a
 * player-shaped UUID in its User-Agent/X-Signage-Agent header (same formats as
 * UserAgentHandler::parseUserAgent()). This is a cheap sanity filter only - it does not
 * verify the UUID against anything, so it is NOT real access control, just a place to
 * plug a real licence/revocation check into later without touching callers.
 */

use App\Framework\Media\FileResponse;

// No autoloader and no .env: nothing here needs them, and skipping both keeps every
// player request (mostly 304 revalidations) cheap, e.g. on a Raspberry Pi next to the player.
// FileResponse has no dependencies and is loaded directly.
$systemDir = dirname(__DIR__);
require_once $systemDir . '/src/Framework/Media/FileResponse.php';

function deliveryFail(int $status): never
{
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header_remove('ETag');
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

// Source = first path segment, none means mediapool. Directories must stay in sync with
// [originals] in config_mediapool.ini and config_playlists.ini. Mediapool files are named
// by their content hash, rendered template items by item id - the id stays the same when
// the item is rendered again, so these files must not be cached as immutable.
const DELIVERY_SOURCES = [
	'mediapool' => ['dir' => 'mediapool/originals',       'name' => '/^[a-f0-9]{32,64}$/',        'immutable' => true],
    'tpl'       => ['dir' => 'playlists/items/originals', 'name' => '/^\d{1,10}$/',        'immutable' => false],
];

if (!preg_match('#^/(?:(tpl)/)?([a-z0-9]+)\.([a-z0-9]{2,5})$#', $_SERVER['PATH_INFO'] ?? '', $m)
    || !isset(FileResponse::MIME[$m[3]]))
    deliveryFail(400);

[, $sourceKey, $checksum, $ext] = $m;
$source = DELIVERY_SOURCES[$sourceKey === '' ? 'mediapool' : $sourceKey];
if (!preg_match($source['name'], $checksum))
    deliveryFail(400);

// mediapool: ETag is the checksum itself, answer 304 without touching the filesystem
if ($source['immutable'] && ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"' . $checksum . '"')
{
    header('ETag: "' . $checksum . '"');
    header('Cache-Control: public, max-age=31536000, immutable');
    http_response_code(304);
    exit;
}

$baseDir = realpath($systemDir . '/var/storage/' . $source['dir']);
if ($baseDir === false)
    deliveryFail(404);

$real = realpath($baseDir . '/' . $checksum . '.' . $ext);
if ($real === false || !str_starts_with($real, $baseDir . '/') || !is_file($real))
	deliveryFail(404);

$size  = filesize($real);
$mtime = filemtime($real);
// checksum already is the content hash, so it alone is a correct, stable ETag.
// Template items change under the same name: size and mtime tell when they did.
$etag = $source['immutable'] ? '"' . $checksum . '"' : '"' . dechex($mtime) . '-' . dechex($size) . '"';

header('ETag: ' . $etag);
if ($source['immutable'])
{
	// Safe to cache forever: same checksum will never resolve to different bytes.
	header('Cache-Control: public, max-age=31536000, immutable');
}
else
{
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('Cache-Control: public, no-cache'); // may be stored, but has to be revalidated by ETag
}

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag)
{
	http_response_code(304);
	exit;
}

// after it is clear it is not 304 to save some bytes in content-disposition
FileResponse::sendFileHeaders($checksum, $ext);

// Apache with mod_xsendfile (flagged by SetEnv GARLIC_XSENDFILE in the Apache config):
// hand the file over, Apache streams it via sendfile() and handles Range and HEAD itself,
// so the PHP worker is free at once instead of being held for the whole download.
// Without the module the PHP streaming below stays as fallback (e.g. ddev without the package).
if (($_SERVER['GARLIC_XSENDFILE'] ?? '') === '1')
{
	header('X-Sendfile: ' . $real);
	exit;
}

$range = $_SERVER['HTTP_RANGE'] ?? '';

// A template may have been rendered again since the interrupted download:
// If-Range with an old ETag/date means send the complete new file.
$ifRange = $_SERVER['HTTP_IF_RANGE'] ?? '';
if ($range !== '' && $ifRange !== '' && $ifRange !== $etag
    && ($source['immutable'] || $ifRange !== gmdate('D, d M Y H:i:s', $mtime) . ' GMT'))
    $range = '';

$partOfFile = FileResponse::resolveRange($range, $size);
if ($partOfFile === null)
{
	header('Content-Range: bytes */' . $size);
	deliveryFail(416);
}
$length = FileResponse::sendRangeHeaders($partOfFile, $size);

if ($_SERVER['REQUEST_METHOD'] === 'HEAD')
	exit;

if (!FileResponse::stream($real, $partOfFile['start'], $length))
	deliveryFail(500);
