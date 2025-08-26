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

use finfo;
use RuntimeException;

/**
 * A wrapper class for handling file information and MIME type detection.
 */
class FileInfoWrapper
{
	private finfo $fileInfo;

	public function __construct()
	{
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		if (!$finfo)
			throw new RuntimeException('Could not create finfo instance');

		$this->fileInfo = $finfo;
	}

	public function __destruct()
	{
		// @phpstan-ignore-next-line // needed for testing
		if (isset($this->fileInfo))
			finfo_close($this->fileInfo);
	}

	public function fileExists(string $path): bool
	{
		return file_exists($path);
	}

	public function detectMimeTypeFromFile(string $path): bool|string
	{
		return finfo_file($this->fileInfo, $path);
	}

	public function detectMimeTypeFromStreamContent(string $streamContent): bool|string
	{
		return finfo_buffer($this->fileInfo, $streamContent);
	}

	public function isStream(mixed $stream): bool
	{
		return (get_resource_type($stream) === 'stream');
	}

	public function getStreamContent(mixed $stream): bool|string
	{
		return stream_get_contents($stream, -1, 0);
	}
}