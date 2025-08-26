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

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Entities\PlayerEntity;
use League\Flysystem\FilesystemException;

/**
 * Handles the generation and management of SMIL index files based on different scenarios
 * such as forbidden, new, unreleased, released, testing, and scenarios related to corrupt content.
 */
class IndexProvider
{
	private readonly Config $config;
	private readonly IndexCreator $indexCreator;
	private string $filePath;

	public function __construct(Config $config, IndexCreator $indexCreator)
	{
		$this->config = $config;
		$this->indexCreator = $indexCreator;
	}

	/**
	 */
	public function handleForbidden(): void
	{
		$this->filePath = $this->config->getConfigValue('defaults', 'player', 'SmilDirectories').'/forbidden.smil';
	}


	/**
	 */
	public function handleNew(): void
	{
		// ToDo TransferHandler create transfer code and send SMIL with code or unreleased
		$this->filePath = $this->config->getConfigValue('defaults', 'player', 'SmilDirectories').'/unreleased.smil';
	}

	/**
	 */
	public function handleUnreleased(): void
	{
		// Todo check  for Transfer code and send smil with it or unreleased
		$this->filePath = $this->config->getConfigValue('defaults', 'player', 'SmilDirectories').'/unreleased.smil';

	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	public function handleReleased(PlayerEntity $playerEntity): void
	{
		if ($playerEntity->getPlaylistId() > 0)
		{
			$this->indexCreator->createForReleasedPlayer($playerEntity,	$this->config);
			$this->filePath = $this->indexCreator->getIndexFilePath();
		}
		else
		{
			$this->filePath = $this->config->getConfigValue('defaults', 'player', 'SmilDirectories').'/released.smil';
		}

	}

	/**
	 */
	public function handleTestSMil(): void
	{
		$this->filePath = $this->config->getConfigValue('tests', 'player', 'SmilDirectories').'/index.smil';
	}

	/**
	 */
	public function handleCorrectSMil(): void
	{
		$this->filePath = $this->config->getConfigValue('simulations', 'player', 'SmilDirectories').'/without_errors.smil';
	}

	/**
	 */
	public function handleCorruptSMIL(): void
	{
		$this->filePath = $this->config->getConfigValue('simulations', 'player', 'SmilDirectories').'/broken_index.smil';
	}

	/**
	 */
	public function handleCorruptContent(): void
	{
		$this->filePath = $this->config->getConfigValue('simulations', 'player', 'SmilDirectories').'/unreachable_content.smil';
	}

	/**
	 */
	public function handleCorruptPrefetchContent(): void
	{
		$this->filePath = $this->config->getConfigValue('simulations', 'player', 'SmilDirectories').'/unreachable_prefetch_content.smil';
	}

	public function getFilePath(): string
	{
		return $this->config->getPaths('systemDir').'/'.$this->filePath;
	}
}