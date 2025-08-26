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

namespace App\Modules\Player\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\IndexCreation\IndexProvider;
use App\Modules\Player\IndexCreation\PlayerDataAssembler;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Throwable;
/*
1. parse User Agent
2. Fetch Player data from DB
3. select SMIL according to player status.
	if there is player status 3 (with License)
		1. check for commands => generate Task scheduler
		2. send correct SMIL

 	1. get the right SMIL depending on player model
 	2. Build SMIL
 	3. Write SMIL if it is different from previous stored
	4. send to player
*/
class PlayerIndexService extends AbstractBaseService
{
	private readonly PlayerDataAssembler $playerDataAssembler;
	private readonly IndexProvider $indexProvider;
	private PlayerEntity $playerEntity;

	public function __construct(PlayerDataAssembler $playerDataAssembler, IndexProvider $indexProvider, LoggerInterface $logger)
	{
		$this->playerDataAssembler = $playerDataAssembler;
		$this->indexProvider       = $indexProvider;
		parent::__construct($logger);
	}

	/**
	 * @param array<string,string> $server
	 */
	public function handleIndexRequest(string $userAgent, array $server, bool $localPlayer): string
	{
		try
		{
			$this->logger->info('Connection from: ' . $userAgent);

			if ($this->playerDataAssembler->parseUserAgent($userAgent))
			{
				$this->playerDataAssembler->setServerData($server);
				$this->determinePlayerEntities($localPlayer);
				$this->handlePlayerStats();
			}
			else
			{
				$this->indexProvider->handleForbidden();
			}

			return $this->indexProvider->getFilePath();
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error fetch Index: ' . $e->getMessage());
			// Todo send Email with exception reason to admin maybe send an prepared error smil to call operator
		}
		return '';
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 */
	private function handlePlayerStats(): void
	{
		switch ($this->playerEntity->getStatus())
		{
			case PlayerStatus::UNREGISTERED->value:
				$this->playerEntity = $this->playerDataAssembler->insertNewPlayer($this->UID);
				$this->indexProvider->handleNew();
				break;
			case PlayerStatus::UNRELEASED->value:
				$this->indexProvider->handleUnreleased();
				break;
			case PlayerStatus::RELEASED->value:
				$this->indexProvider->handleReleased($this->playerEntity);
				break;
			case PlayerStatus::DEBUG_FTP->value:
				$this->indexProvider->handleTestSMil();
				break;
			case PlayerStatus::TEST_SMIL_OK->value:
				$this->indexProvider->handleCorrectSMil();
				break;
			case PlayerStatus::TEST_SMIL_ERROR->value:
				$this->indexProvider->handleCorruptSMIL();
				break;
			case PlayerStatus::TEST_NO_CONTENT->value:
				$this->indexProvider->handleCorruptContent();
				break;
			case PlayerStatus::TEST_NO_PREFETCH->value:
				$this->indexProvider->handleCorruptPrefetchContent();
				break;
			default:
				throw new ModuleException('player_index', 'Unknown player status: ' . $this->playerEntity->getStatus());
		}
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 */
	private function determinePlayerEntities(bool $localPlayer): void
	{
		if (!$localPlayer)
			$this->playerEntity = $this->playerDataAssembler->fetchDatabase();
		else
			$this->playerEntity = $this->playerDataAssembler->handleLocalPlayer();

	}
}