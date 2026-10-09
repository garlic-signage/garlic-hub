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
 * GET parameters: c (checksum), ext, v (thumb|orig), exp (unix timestamp), sig (hmac)
 * Signature handling: see MediaUrlSigner
 */

use App\Framework\Media\MediaUrlSigner;

require dirname(__DIR__) . '/src/Framework/Media/MediaUrlSigner.php';

const MEDIA_MIME = [
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png'  => 'image/png',
	'gif'  => 'image/gif',
	'webp' => 'image/webp',
	'svg'  => 'image/svg+xml',
	'mp4'  => 'video/mp4',
	'webm' => 'video/webm',
	'ogv'  => 'video/ogg',
	'mp3'  => 'audio/mpeg',
	'wav'  => 'audio/wav',
	'ogg'  => 'audio/ogg',
	'pdf'  => 'application/pdf',
	'wgt'  => 'application/octet-stream',
];
const MEDIA_VARIANT_DIR = ['thumb' => 'thumbs', 'orig' => 'originals'];

function mediaFail(int $status): never
{
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
	exit;
}

function mediaSecret(string $systemDir): string
{
	$secret = getenv('APP_SECRET');
	if (($secret === false || $secret === '') && is_readable($systemDir . '/.env'))
	{
		foreach (file($systemDir . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
		{
			if (str_starts_with($line, 'APP_SECRET='))
			{
				$secret = trim(substr($line, 11), " \t\"'");
				break;
			}
		}
	}
	if (!is_string($secret) || $secret === '')
		mediaFail(500);

	return $secret;
}

$systemDir = dirname(__DIR__);

$checksum = $_GET['c']   ?? '';
$ext      = $_GET['ext'] ?? '';
$variant  = $_GET['v']   ?? '';

if (!is_string($checksum) || !is_string($ext) || !is_string($variant)
	|| !preg_match('/^[a-f0-9]{32,64}$/', $checksum) || !isset(MEDIA_MIME[$ext]) || !isset(MEDIA_VARIANT_DIR[$variant]))
	mediaFail(400);

if (!MediaUrlSigner::verify($_GET, mediaSecret($systemDir)))
	mediaFail(403);

$baseDir = $systemDir . '/var/storage/mediapool/' . MEDIA_VARIANT_DIR[$variant];
$path    = $baseDir . '/' . $checksum . '.' . $ext;
$real    = realpath($path);
if ($real === false || !str_starts_with($real, $baseDir . '/') || !is_file($real))
	mediaFail(404);

$size  = filesize($real);
$mtime = filemtime($real);
$etag  = '"' . md5($checksum . $size . $mtime) . '"';

header('Content-Type: ' . MEDIA_MIME[$ext]);
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Accept-Ranges: bytes');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Cache-Control: private, max-age=' . min(max((int) $_GET['exp'] - time(), 0), 86400));

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
		mediaFail(416);
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
		mediaFail(416);
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
	mediaFail(500);

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
