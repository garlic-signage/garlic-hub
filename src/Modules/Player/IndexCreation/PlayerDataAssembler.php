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
use App\Modules\Player\Entities\PlayerEntityFactory;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\Repositories\PlayerIndexRepository;
use Doctrine\DBAL\Exception;

/**
 * This class is responsible for assembling and managing player data.
 * It interacts with various components such as user agent handler, repositories,
 * configuration settings, and entity factories to process and retrieve player-related information.
 */
class PlayerDataAssembler
{
	/**
	 * @var array<string,string>
	 */
	// @phpstan-ignore-next-line
	private array $serverData;

	private UserAgentHandler $userAgentHandler;
	private readonly PlayerIndexRepository $playerRepository;
	private readonly Config $config;
	private readonly PlayerEntityFactory $playerEntityFactory;

	public function __construct(UserAgentHandler $userAgentHandler,
		PlayerIndexRepository $playerRepository,
		Config $config,
		PlayerEntityFactory $playerEntityFactory)
	{
		$this->userAgentHandler = $userAgentHandler;
		$this->playerRepository = $playerRepository;
		$this->config           = $config;
		$this->playerEntityFactory = $playerEntityFactory;
	}


	public function parseUserAgent(string $userAgent): bool
	{
		$this->userAgentHandler->parseUserAgent($userAgent);
		if ($this->userAgentHandler->getModel() === PlayerModel::UNKNOWN)
			return false;

		return true;
	}

	/**
	 * @param array<string,string> $serverData
	 */
	public function setServerData(array $serverData): void
	{
		$this->serverData = $serverData;
	}

	/**
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function handleLocalPlayer(): PlayerEntity
	{
		$result = $this->playerRepository->findPlayerById(1);

		if ($result === [])
		{
			$saveData = $this->buildInsertArray();
			// we need this to init playerEntity not with normal default values.
			if ($this->config->getEnv('APP_CONTAINER') === Config::APP_CONTAINER_DOCKER)
				$apiEndpoint = 'http://host.docker.internal:8080/v2';
			else
				$apiEndpoint = 'http://localhost:8080/v2';

			$result   = [
				'player_id'  => 1,
				'status' => PlayerStatus::RELEASED->value,
				'api_endpoint' => $apiEndpoint,
				'is_intranet' => true,
				'licence_id' => 1
			];

			$id = $this->playerRepository->insertPlayer(array_merge($saveData, $result));
			if ($id === 0)
				throw new ModuleException('player_index', 'Failed to insert local player');
		}
		elseif ($result['uuid'] !== $this->userAgentHandler->getUuid())
			throw new ModuleException('player_index', 'Wrong Uuid for local player: '. $result['uuid'] .' != Agent'. $this->userAgentHandler->getUuid());

		$this->playerRepository->updateLastAccess(1);

		return $this->playerEntityFactory->create($result, $this->userAgentHandler);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 */
	public function insertNewPlayer(int $ownerId): PlayerEntity
	{
		$saveData = $this->buildInsertArray($ownerId);

		if($this->config->getEdition() === Config::PLATFORM_EDITION_EDGE)
			$saveData = array_merge($saveData, ['status' => PlayerStatus::RELEASED->value, 'licence_id' => 1]);

		// Todo: integrate license system to handle free licenses

		$id = $this->playerRepository->insertPlayer($saveData);
		if ($id === 0)
			throw new ModuleException('player_index', 'Failed to insert local player');

		return $this->playerEntityFactory->create($saveData, $this->userAgentHandler);
	}

	/**
	 * @throws Exception
	 */
	public function fetchDatabase(): PlayerEntity
	{
		$result = $this->playerRepository->findPlayerByUuid($this->userAgentHandler->getUuid());

		if ($result !== [])
			$this->playerRepository->updateLastAccess((int) $result['player_id']);

		return $this->playerEntityFactory->create($result, $this->userAgentHandler);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildInsertArray(int $ownerId = 1): array
	{
		return [
			'uuid'        => $this->userAgentHandler->getUuid(),
			'player_name' => $this->userAgentHandler->getName(),
			'firmware'    => $this->userAgentHandler->getFirmware(),
			'model'       => $this->userAgentHandler->getModel()->value,
			'playlist_id' => 0,
			'UID'         => $ownerId,
			'status'      => PlayerStatus::UNRELEASED->value,
			'refresh'     => 900,
			'licence_id'  => 0,
			'commands'    => [],
			'reports'     => [],
			'location_data' => [],
			'location_longitude' => '',
			'location_latitude' => '',
			'categories' => [],
			'properties' => [],
			'remote_administration' => [],
			'screen_times' => []
		];
	}

/*	private function buildUpdateArray(): array
	{
		return [
			'player_name' => $this->userAgentHandler->getName(),
			'firmware'    => $this->userAgentHandler->getFirmware(),
			'model'       => $this->userAgentHandler->getModel()->value,
			'last_access' => date('Y-m-d H:i:s')
		];
	}
*/
}