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

namespace App\Framework\Media;

use App\Framework\Exceptions\ModuleException;
use InvalidArgumentException;

/**
 * MimeTypeDetector class is responsible for detecting MIME types of files
 * and streams, as well as determining file extensions from MIME types.
 */
class MimeTypeDetector
{
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

	/**
	 * @throws ModuleException
	 */
	public function detectFromStringContent(string $content): string
	{
		$mimeType = $this->fileInfoWrapper->detectMimeTypeFromStreamContent($content);
		if (!is_string($mimeType))
			throw new ModuleException('mediapool', 'MIME-Type could not be detected from string content.');

		return $mimeType;
	}

}