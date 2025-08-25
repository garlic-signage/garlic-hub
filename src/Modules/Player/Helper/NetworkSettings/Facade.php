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

namespace App\Modules\Player\Helper\NetworkSettings;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Facade class provides an interface to manage player-related functionality.
 * It supports initializing translation and session handlers, loading and editing player data,
 * managing form configurations, updating network data, handling service errors, and preparing UI templates.
 * This class collaborates with multiple services like PlayerService, Translator, Builder, and Parameters to perform its tasks.
 */
class Facade
{
	private readonly Builder $settingsFormBuilder;
	private readonly PlayerService $playerService;
	private readonly Parameters $settingsParameters;
	private Translator $translator;
	/**
	 * @var array{player_id:int, player_name:string, model:int, is_intranet:int, api_endpoint:string}|array<empty,empty>
	 */
	private array $player;

	 public function __construct(Builder $settingsFormBuilder, PlayerService $playerService, Parameters $settingsParameters)
	{
		$this->settingsFormBuilder = $settingsFormBuilder;
		$this->playerService       = $playerService;
		$this->settingsParameters  = $settingsParameters;
	}

	public function init(Translator $translator, Session $session): void
	{
		$this->translator = $translator;
		/** @var array{UID: int} $user */
		$user = $session->get('user');
		$this->playerService->setUID($user['UID']);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function loadPlayerForEdit(int $playerId): static
	{
		/** @var array{player_id:int, player_name:string, model:int, is_intranet:int, api_endpoint:string}|array<empty,empty> $player */
		$player = $this->playerService->fetchAclCheckedPlayerData($playerId);
		$this->player = $player;

		return $this;
	}

	/**
	 * @return array{player_id:int, player_name:string, model:int, is_intranet:int, api_endpoint:string}|array<empty,empty>
	 */
	public function getPlayer(): array
	{
		return $this->player;
	}

	/**
	 * @param array{player_id:int, player_name:string, is_intranet:int, api_endpoint:string}|array<empty,empty> $post
	 * @return string[]
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function configureFormParameter(array $post): array
	{
		if (isset($post['player_id']) && $post['player_id'] > 0)
		{
			$this->loadPlayerForEdit((int) $post['player_id']);
			if ($this->player === [])
				return [$this->translator->translate('player_not_found', 'player')];

			/** @var array{player_id:int, player_name:string, is_intranet:int, api_endpoint:string} $post */
			return $this->settingsFormBuilder->handleUserInput($post);
		}

		return [$this->translator->translate('player_not_found', 'player')];
	}

	/**
	 * @throws Exception
	 */
	public function storeNetworkData(): int
	{
		/** @var array{api_endpoint:string, is_intranet: int} $saveData */
		$saveData  = array_combine(
			$this->settingsParameters->getInputParametersKeys(),
			$this->settingsParameters->getInputValuesArray()
		);

		return $this->playerService->updatePlayer((int) $this->player['player_id'], $saveData);
	}

	/**
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function getUserServiceErrors(): array
	{
		$errors     = $this->playerService->getErrorMessages();
		$translated =[];
		foreach ($errors as $error)
		{
			$translated[] = $this->translator->translate($error, 'player');
		}
		return $translated;
	}

	/**
	 * @param array{username?:string, email?:string, locale?: string, password?:string, password_confirm?: string}  $post
	 * @return array<string,mixed>
	 *
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function prepareUITemplate(array $post): array
	{
		/** @var string $name */ // this will not be called when $this->>player is empty
		$name = $this->player['player_name'];
		$title = $this->translator->translate('api_connectivity', 'player');
		$dataSections                      = $this->settingsFormBuilder->buildForm($post);
		$dataSections['title']             = $title.': '.$name;
		$dataSections['additional_css']    = ['/css/player/edit.css'];
		$dataSections['footer_modules']    = [];
		$dataSections['template_name']     = 'player/edit';
		$dataSections['form_action']       = '/player/connectivity';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $dataSections;
	}

}