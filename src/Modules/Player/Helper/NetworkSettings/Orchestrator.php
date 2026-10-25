<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Player\Helper\NetworkSettings;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;

class Orchestrator
{
	public function __construct(
		private readonly PlayerService       $playerService,
		private readonly FormInputHandler    $formInputHandler,
		private readonly SettingsFormBuilder $settingsFormBuilder
	) {}

	/**
	 * Returns an empty array when the player does not exist or is not editable for the current user.
	 *
	 * @return array{player_id:int, player_name:string, model:int, is_intranet:int, api_endpoint:string}|array{}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function loadPlayer(int $playerId): array
	{
		if ($playerId <= 0)
			return [];

		try
		{
			/** @var array{player_id:int, player_name:string, model:int, is_intranet:int, api_endpoint:string} $player */
			$player = $this->playerService->fetchAclCheckedPlayerData($playerId);
		}
		catch (NotFoundException|AccessDeniedException)
		{
			// the html page redirects with a flash message instead of the standard error response
			return [];
		}

		return $player;
	}

	/**
	 * @param array<string,mixed> $networkData
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(array $networkData): array
	{
		return $this->settingsFormBuilder->buildForm($networkData);
	}

	/**
	 * The player must be loaded with loadPlayer() before, so the ACL is checked.
	 *
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[], values?: array<string,mixed>}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function store(int $playerId, array $post): array
	{
		$errors = $this->formInputHandler->validate($post);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors, 'values' => $this->formInputHandler->getParsed()];

		/** @var array{api_endpoint:string, is_intranet: int} $saveData */
		$saveData = $this->formInputHandler->getParsed();
		// a save without changes affects no rows, but is not a failure
		$this->playerService->updatePlayer($playerId, $saveData);

		return ['success' => true];
	}
}
