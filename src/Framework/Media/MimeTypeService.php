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
class MimeTypeService
{
	public function __construct(
		private readonly MimeTypeDetector $mimeTypeDetector,
		private readonly MimeTypeExtensionMapper $mimeTypeExtensionMapper,
	)
	{}

	/**
	 * @throws ModuleException
	 */
	public function detectFromFile(string $filePath): string
	{
		return $this->mimeTypeDetector->detectFromFile($filePath);
	}

	/**
	 * @throws ModuleException
	 */
	public function detectFromStream(mixed $stream): string
	{
		return $this->mimeTypeDetector->detectFromStream($stream);
	}

	/**
	 * @throws ModuleException
	 */
	public function detectFromStringContent(string $content): string
	{
		if ($content === '')
			throw new InvalidArgumentException('Content is empty.');

		return $this->mimeTypeDetector->detectFromStringContent($content);
	}

	public function determineExtensionByMimeType(string $mimeType): string
	{
		return $this->mimeTypeExtensionMapper->determineExtension($mimeType);
	}

}