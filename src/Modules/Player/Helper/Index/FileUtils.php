<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Player\Helper\Index;

use App\Framework\Exceptions\ModuleException;
use Slim\Psr7\Stream;

/**
 * Utility class for handling file operations.
 */
class FileUtils
{
	/**
	 * @throws ModuleException
	 */
	public function getFileMTime(string $filepath): int
	{
		$time = filemTime($filepath);
		if ($time === false)
			throw new ModuleException('player_index', 'FileMTime error with: '.$filepath);

		return $time;
	}

	/**
	 * @throws ModuleException
	 */
	public function getETag(string $filepath): string
	{
		$content = file_get_contents($filepath);
		if ($content === false)
			throw new ModuleException('player_index', 'File get content error with: '.$filepath);

		return md5($content);
	}

	/**
	 * @throws ModuleException
	 */
	public function getFileSize(string $filepath): int
	{
		$size = filesize($filepath);
		if ($size === false)
			throw new ModuleException('player_index', 'Filesize error with: '.$filepath);

		return $size;
	}

	/**
	 * @throws ModuleException
	 */
	public function createStream(string $filepath): Stream
	{
		$resource = fopen($filepath, 'rb');
		if ($resource === false)
			throw new ModuleException('player_index', 'Stream  open error with: '.$filepath);

		return new Stream($resource);
	}
}