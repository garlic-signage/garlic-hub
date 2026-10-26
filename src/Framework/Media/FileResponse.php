<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace App\Framework\Media;

/**
 * What public/media.php (browsers) and public/delivery.php (players) have in common when they deliver a file:
 * the media types, the headers which make a file harmless, the Range header and the streaming.
 *
 * Without any dependency: delivery.php loads this file directly, without autoloader and .env, so every
 * request of a player stays cheap.
 */
final class FileResponse
{
	public const array MIME = [
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
		'opus' => 'audio/ogg',
		'wav'  => 'audio/wav',
		// Videos
		'mp4'  => 'video/mp4',
		'avi'  => 'video/x-msvideo',
		'mkv'  => 'video/x-matroska',
		'webm' => 'video/webm',
		'mov'  => 'video/quicktime',
		'mpg'  => 'video/mpeg',
		// PDF
		'pdf'  => 'application/pdf',
		// Widgets and miscellaneous: delivered as download
		'wgt'  => 'application/octet-stream',
		'zip'  => 'application/zip',
		'json' => 'application/json',
		'xml'  => 'application/xml',
		'rss'  => 'application/rss+xml',
		'atom' => 'application/atom+xml',
		'apk'  => 'application/vnd.android.package-archive',
		'smil' => 'application/smil',
		'txt'  => 'text/plain',
		'csv'  => 'text/csv',
		'bin'  => 'application/octet-stream',
	];

	/** extensions which are delivered as download, never shown in the browser */
	public const array DOWNLOAD = ['wgt', 'zip', 'json', 'xml', 'rss', 'atom', 'apk', 'smil', 'txt', 'csv', 'bin'];

	/**
	 * The media type, a download for everything which is no media, no sniffing, no scripts (a SVG is shown as
	 * a picture, not run as a page), ranges allowed. The extension has to be a key of MIME.
	 */
	public static function sendFileHeaders(string $name, string $extension): void
	{
		header('Content-Type: ' . self::MIME[$extension]);
		if (in_array($extension, self::DOWNLOAD, true))
			header('Content-Disposition: attachment; filename="' . $name . '.' . $extension . '"');
		header('X-Content-Type-Options: nosniff');
		header('Content-Security-Policy: sandbox');
		header('Accept-Ranges: bytes');
	}

	/**
	 * The part of the file a Range header asks for. A missing, unsupported or malformed Range (e.g. multi-range)
	 * means the whole file. Null: the range is outside the file (416).
	 *
	 * @return array{start: int, end: int, partial: bool}|null
	 */
	public static function resolveRange(string $range, int $size): ?array
	{
		if ($range === '' || !preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) || ($m[1] === '' && $m[2] === ''))
			return ['start' => 0, 'end' => $size - 1, 'partial' => false];

		$end = $size - 1;
		if ($m[1] === '') // suffix range: the last N bytes
			$start = max($size - (int) $m[2], 0);
		else
		{
			$start = (int) $m[1];
			if ($m[2] !== '')
				$end = min((int) $m[2], $size - 1);
		}

		if ($start > $end || $start >= $size)
			return null;

		return ['start' => $start, 'end' => $end, 'partial' => true];
	}

	/**
	 * 206 and Content-Range for a part, Content-Length always.
	 *
	 * @param array{start: int, end: int, partial: bool} $range
	 * @return int the number of bytes to send
	 */
	public static function sendRangeHeaders(array $range, int $size): int
	{
		if ($range['partial'])
		{
			http_response_code(206);
			header('Content-Range: bytes ' . $range['start'] . '-' . $range['end'] . '/' . $size);
		}

		$length = $range['end'] - $range['start'] + 1;
		header('Content-Length: ' . $length);

		return $length;
	}

	/**
	 * Copies the bytes in C instead of a PHP read/echo loop. The output buffers (incl. zlib compression) are
	 * closed first: they would break Content-Length and Range or hold the whole file in memory.
	 */
	public static function stream(string $path, int $start, int $length): bool
	{
		while (ob_get_level() > 0)
			ob_end_clean();

		$file = fopen($path, 'rb');
		if ($file === false)
			return false;

		$output = fopen('php://output', 'wb');
		if ($output === false)
		{
			fclose($file);
			return false;
		}

		set_time_limit(0);
		stream_copy_to_stream($file, $output, $length, $start);
		fclose($file);
		fclose($output);

		return true;
	}
}
