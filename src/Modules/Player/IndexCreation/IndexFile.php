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

namespace App\Modules\Player\IndexCreation;

use App\Framework\Exceptions\ModuleException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;

/**
 * Class responsible for managing index files within the filesystem.
 */
class IndexFile
{
	private readonly Filesystem $fileSystem;
	private LoggerInterface $logger;
	private string $indexFilePath;

	/**
	 * @param Filesystem      $fileSystem
	 * @param LoggerInterface $logger
	 */
	public function __construct(Filesystem $fileSystem, LoggerInterface $logger)
	{
		$this->fileSystem = $fileSystem;
		$this->logger = $logger;
	}

	public function setIndexFilePath(string $indexFilePath): IndexFile
	{
		$this->indexFilePath = $indexFilePath;
		return $this;
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	public function handleIndexFile(string $newContent): static
	{
		$oldContent = $this->fetchOldIndex();

		if (!empty($newContent) && $newContent !== $oldContent)
		{
			$this->writeNewIndex($newContent);
		}
		elseif (empty($oldContent))
		{
			$this->logger->warning('Index content generation failed found old index "' . $this->indexFilePath . '" and played this instead');
			throw new ModuleException('player_index', 'Index content generation failed and no old index file present');
		}

		return $this;
	}

	/**
	 * @throws FilesystemException
	 */
	private function writeNewIndex(string $content): void
	{
		$this->fileSystem->write($this->indexFilePath, $content);
	}

	/**
	 * @throws FilesystemException
	 */
	private function fetchOldIndex(): string
	{
		if ($this->fileSystem->fileExists($this->indexFilePath))
			return $this->fileSystem->read($this->indexFilePath);
		else
			return '';
	}
}