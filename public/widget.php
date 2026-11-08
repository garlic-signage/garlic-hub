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
 * Delivers the files inside a mediapool widget (.wgt) for the preview in the browser, straight from the
 * archive, nothing is unpacked. Independent of the CMS bootstrap (no container, session or DB) like media.php.
 *
 * Called as widget.php/<checksum>/<exp>/<sig>/<path inside the widget>?<preferences> (PATH_INFO).
 * Expiry and signature are part of the path, so the relative urls of the widget are signed, too.
 * Without a path it redirects to the start file of config.xml. Preferences the query does not set
 * get the value of config.xml (value or ia:default).
 *
 * The widget is foreign code: the CSP sandbox gives it an own, opaque origin, so it reaches neither the
 * session nor the pages of the hub, wherever it is opened. Signature handling: see MediaUrlSigner::widgetUrl
 */

use App\Framework\Media\MediaUrlSigner;
use App\Framework\Utils\Widget\ConfigXML;

$systemDir = dirname(__DIR__);

require $systemDir . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($systemDir)->safeLoad();

// must stay in sync with originals of the [directories] section of config_mediapool.ini
const WIDGET_DIR = 'mediapool/originals';

const WIDGET_MIME = [
	'html'  => 'text/html; charset=utf-8',
	'htm'   => 'text/html; charset=utf-8',
	'js'    => 'text/javascript; charset=utf-8',
	'mjs'   => 'text/javascript; charset=utf-8',
	'css'   => 'text/css; charset=utf-8',
	'json'  => 'application/json',
	'xml'   => 'application/xml',
	'txt'   => 'text/plain; charset=utf-8',
	'svg'   => 'image/svg+xml',
	'png'   => 'image/png',
	'jpg'   => 'image/jpeg',
	'jpeg'  => 'image/jpeg',
	'gif'   => 'image/gif',
	'webp'  => 'image/webp',
	'avif'  => 'image/avif',
	'ico'   => 'image/x-icon',
	'woff'  => 'font/woff',
	'woff2' => 'font/woff2',
	'ttf'   => 'font/ttf',
	'otf'   => 'font/otf',
	'mp4'   => 'video/mp4',
	'webm'  => 'video/webm',
	'mp3'   => 'audio/mpeg',
	'ogg'   => 'audio/ogg',
	'wav'   => 'audio/wav',
];

function widgetFail(int $status): never
{
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
	exit;
}

/**
 * A relative path inside the archive: no absolute path, no backslash, no . or .. segment.
 */
function isWidgetPath(string $path): bool
{
	if ($path === '' || strlen($path) > 512 || str_contains($path, "\0") || str_contains($path, '\\'))
		return false;

	foreach (explode('/', $path) as $segment)
	{
		if ($segment === '' || $segment === '.' || $segment === '..')
			return false;
	}

	return true;
}

/**
 * @param array<string, mixed> $query
 * @return array{start: string, query: array<string, mixed>}
 */
function determineStart(ZipArchive $zip, array $query): array
{
	$start = 'index.html';
	$configData = $zip->getFromName('config.xml');
	if ($configData === false)
		return ['start' => $start, 'query' => $query];

	try
	{
		$configXml = new ConfigXML();
		$configXml->load($configData)->parseBasic()->parsePreferences();
		if (isWidgetPath($configXml->getContent()))
			$start = $configXml->getContent();

		// W3C: value, IAdea: ia:default
		foreach ($configXml->getPreferences() as $name => $preference)
		{
			if (array_key_exists($name, $query) || !is_array($preference))
				continue;

			$default = $preference['value'] ?? $preference['default'] ?? null;
			if (is_string($default) && $default !== '')
				$query[$name] = $default;
		}
	}
	catch (Throwable)
	{
		// a broken config.xml: the start file of the standard and the query as it is
	}

	return ['start' => $start, 'query' => $query];
}

if (!preg_match('#^/([a-f0-9]{32,64})/(\d{1,12})/([a-f0-9]{64})/(.*)$#', $_SERVER['PATH_INFO'] ?? '', $m))
	widgetFail(400);

[, $checksum, $exp, $sig, $path] = $m;

$secret = $_ENV['APP_SECRET'] ?? (getenv('APP_SECRET') ?: '');
if (!is_string($secret) || $secret === '')
	widgetFail(500);

if (!MediaUrlSigner::verify(['c' => $checksum, 'ext' => 'wgt', 'v' => MediaUrlSigner::VARIANT_WIDGET, 'exp' => $exp, 'sig' => $sig], $secret))
	widgetFail(403);

$baseDir = realpath($systemDir . '/var/storage/' . WIDGET_DIR);
if ($baseDir === false)
	widgetFail(404);

$real = realpath($baseDir . '/' . $checksum . '.wgt');
if ($real === false || !str_starts_with($real, $baseDir . '/') || !is_file($real))
	widgetFail(404);

$zip = new ZipArchive();
if ($zip->open($real, ZipArchive::RDONLY) !== true)
	widgetFail(404);

// the widget, its files and the feeds it loads never learn the signed url
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox allow-scripts; frame-ancestors 'self'");

if ($path === '')
{
	$target = determineStart($zip, $_GET);
	$location = implode('/', array_map('rawurlencode', explode('/', $target['start'])));
	if ($target['query'] !== [])
		$location .= '?' . http_build_query($target['query'], '', '&', PHP_QUERY_RFC3986);

	header('Cache-Control: no-store');
	header('Location: ' . $location, true, 302);
	exit;
}

if (!isWidgetPath($path))
	widgetFail(400);

$stat = $zip->statName($path);
if ($stat === false)
	widgetFail(404);

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
header('Content-Type: ' . (WIDGET_MIME[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . $stat['size']);
// the sandbox gives the widget the origin "null": its modules and fonts are loaded with CORS
header('Access-Control-Allow-Origin: *');
header('Cache-Control: private, max-age=' . min(max((int) $exp - time(), 0), 3600));

if ($_SERVER['REQUEST_METHOD'] === 'HEAD')
	exit;

$stream = $zip->getStream($path);
if ($stream === false)
	widgetFail(500);

while (ob_get_level() > 0)
	ob_end_clean();

fpassthru($stream);
fclose($stream);
