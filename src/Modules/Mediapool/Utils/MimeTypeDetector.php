<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Mediapool\Utils;

use App\Framework\Exceptions\ModuleException;
use InvalidArgumentException;

/**
 * MimeTypeDetector class is responsible for detecting MIME types of files
 * and streams, as well as determining file extensions from MIME types.
 */
class MimeTypeDetector
{
	/** @var array<string,string>  */
	private array $preferredMimeTypes = [
		// Images
		'image/jpeg'            => 'jpg',
		'image/avif'            => 'avif',
		'image/png'             => 'png',
		'image/gif'             => 'gif',
		'image/webp'            => 'webp',
		'image/svg+xml'         => 'svg',
		'image/bmp'             => 'bmp',
		'image/x-bmp'           => 'bmp',
		'image/x-ms-bmp'        => 'bmp',
		'image/tiff'            => 'tif',

		// Audios
		'audio/mpeg'            => 'mp3',
		'audio/mp4' 			=> 'mp4',
		'audio/ogg' 			=> 'ogg',
		'audio/opus' 	        => 'opus',
		'audio/wav'             => 'wav',

		// Videos
		'video/mp4'             => 'mp4',
		'video/x-msvideo'       => 'avi',
		'video/x-matroska'      => 'mkv',
		'video/webm'            => 'webm',
		'video/ogg'             => 'ogg',
		'video/quicktime'       => 'mov',
		'video/mpeg'            => 'mpg',

		// PDF
		'application/pdf'       => 'pdf',

		// Widgets
		'application/widget'       => 'wgt',
		'application/octet-stream' => 'wgt',

		// Miscellaneous
		'application/zip'			=> 'zip',
		'application/json'			=> 'json',
		'application/xml'			=> 'xml',
		'application/rss+xml'		=> 'rss',
		'application/atom+xml'		=> 'atom',
		'application/vnd.android.package-archive' => 'apk',
		'application/smil'			=> 'smil',
		'text/xml'			        => 	'xml',
		'text/plain'			    => 'txt',
		'text/csv'			        => 'csv',
	];
	private readonly FileInfoWrapper $fileInfoWrapper;

	public function __construct(FileInfoWrapper $fileInfoWrapper)
	{
		$this->fileInfoWrapper = $fileInfoWrapper;
	}

	/**
	 * @throws ModuleException
	 */
	public function detectFromFile(string $filePath): string
	{
		if (!$this->fileInfoWrapper->fileExists($filePath))
			throw new InvalidArgumentException("File '$filePath' not exists.");

		// exception for the digital signage widgets
		if (pathinfo($filePath, PATHINFO_EXTENSION) === 'wgt')
			return 'application/widget';

		$mimeType = $this->fileInfoWrapper->detectMimeTypeFromFile($filePath);
		if (!is_string($mimeType))
			throw new ModuleException('mediapool', "MIME-Type for '$filePath' could not be detected.");

		return $mimeType;
	}

	/**
	 * @throws ModuleException
	 */
	public function detectFromStream(mixed $stream): string
	{
		if (!$this->fileInfoWrapper->isStream($stream))
			throw new InvalidArgumentException('Invalid stream.');

		$content = $this->fileInfoWrapper->getStreamContent($stream);
		if (!is_string($content))
			throw new ModuleException('mediapool','Stream was not readable.');

		$mimeType = $this->fileInfoWrapper->detectMimeTypeFromStreamContent($content);
		if (!is_string($mimeType))
			throw new ModuleException('mediapool', 'MIME-Type could not be detected from stream.');

		return $mimeType;
	}

	public function determineExtensionByType(string $mimeType): string
	{
		return $this->preferredMimeTypes[$mimeType] ?? 'bin';
	}
}