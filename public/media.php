<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
 * Standalone delivery of mediapool files from var/storage/mediapool.
 * Deliberately independent of the CMS bootstrap (no container, session or DB).
 *
 * Called as media.php/<variant>/<name>.<ext>?exp=<unix timestamp>&sig=<hmac> (PATH_INFO).
 * The file name has to be part of the path: clients take the media type from the extension.
 * Signature handling: see MediaUrlSigner
 */

use App\Framework\Media\FileResponse;
use App\Framework\Media\MediaUrlSigner;

$systemDir = dirname(__DIR__);

require $systemDir . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($systemDir)->safeLoad();

// Directories below var/storage, must stay in sync with the [directories] sections of
// config_mediapool.ini, config_templates.ini and config_playlists.ini.
// Mediapool files are named by their content hash, templates and playlist items by numeric id.
const MEDIA_VARIANT_DIR = [
	'thumb'      => 'mediapool/thumbs',
	'orig'       => 'mediapool/originals',
	'tpl-thumb'  => 'templates/thumbs',
    'tpl-orig'   => 'templates/originals',
	'item-thumb' => 'playlists/items/thumbs',
    'item-orig'  => 'playlists/items/originals',
];
const MEDIA_NAME_PATTERN = [
	'thumb'      => '/^[a-f0-9]{32,64}$/',
	'orig'       => '/^[a-f0-9]{32,64}$/',
	'tpl-thumb'  => '/^\d{1,10}$/',
    'tpl-orig'   => '/^\d{1,10}$/',
	'item-thumb' => '/^\d{1,10}$/',
    'item-orig'  => '/^\d{1,10}$/',
];

const MEDIA_IMMUTABLE = ['thumb' => true, 'orig' => true];

function mediaFail(int $status): never
{
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
	exit;
}

if (!preg_match('#^/([a-z-]+)/([a-z0-9]+)\.([a-z0-9]{2,5})$#', $_SERVER['PATH_INFO'] ?? '', $m))
	mediaFail(400);

[, $variant, $checksum, $ext] = $m;

if (!isset(MEDIA_VARIANT_DIR[$variant]) || !preg_match(MEDIA_NAME_PATTERN[$variant], $checksum) || !isset(FileResponse::MIME[$ext]))
	mediaFail(400);

$secret = $_ENV['APP_SECRET'] ?? (getenv('APP_SECRET') ?: '');
if (!is_string($secret) || $secret === '')
	mediaFail(500);

if (!MediaUrlSigner::verify(['c' => $checksum, 'ext' => $ext, 'v' => $variant] + $_GET, $secret))
	mediaFail(403);

$baseDir = realpath($systemDir . '/var/storage/' . MEDIA_VARIANT_DIR[$variant]);
if ($baseDir === false)
	mediaFail(404);

$real = realpath($baseDir . '/' . $checksum . '.' . $ext);
if ($real === false || !str_starts_with($real, $baseDir . '/') || !is_file($real))
	mediaFail(404);

$size  = filesize($real);
$mtime = filemtime($real);
$immutable = isset(MEDIA_IMMUTABLE[$variant]);
$etag = $immutable ? '"' . $checksum . '"' : '"' . dechex($mtime) . '-' . dechex($size) . '"';

FileResponse::sendFileHeaders($checksum, $ext);
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
if ($immutable)
    header('Cache-Control: private, max-age=' . min(max((int) $_GET['exp'] - time(), 0), 86400));
else
    header('Cache-Control: private, no-cache');

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag)
{
	http_response_code(304);
	exit;
}

$partOfFile = FileResponse::resolveRange($_SERVER['HTTP_RANGE'] ?? '', $size);
if ($partOfFile === null)
{
	header('Content-Range: bytes */' . $size);
	mediaFail(416);
}
$length = FileResponse::sendRangeHeaders($partOfFile, $size);

if ($_SERVER['REQUEST_METHOD'] === 'HEAD')
	exit;

if (!FileResponse::stream($real, $partOfFile['start'], $length))
	mediaFail(500);
