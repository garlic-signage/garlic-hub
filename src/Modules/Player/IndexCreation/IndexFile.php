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
use Throwable;

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

		if ($newContent !== '' && $newContent !== $oldContent)
		{
			$this->writeNewIndex($newContent);

			return $this;
		}

		if ($newContent === '')
		{
			$this->logger->warning('Index content generation failed for "' . $this->indexFilePath . '"' . ($oldContent !== '' ? ', the old index is played instead' : ''));
			if ($oldContent === '')
				throw new ModuleException('player_index', 'Index content generation failed and no old index file present');
		}

		return $this;
	}

	/**
	 * A player which reads the index while it is written must get the old or the new file, never a half one:
	 * the content is written beside and then put in place.
	 *
	 * @throws FilesystemException
	 */
	private function writeNewIndex(string $content): void
	{
		$temporary = $this->indexFilePath . '.' . bin2hex(random_bytes(4)) . '.tmp';
		try
		{
			$this->fileSystem->write($temporary, $content);
			$this->fileSystem->move($temporary, $this->indexFilePath);
		}
		catch (Throwable $e)
		{
			if ($this->fileSystem->fileExists($temporary))
				$this->fileSystem->delete($temporary);
			throw $e;
		}
	}

	private function fetchOldIndex(): string
	{
		if ($this->fileSystem->fileExists($this->indexFilePath))
			return $this->fileSystem->read($this->indexFilePath);
		else
			return '';
	}
}