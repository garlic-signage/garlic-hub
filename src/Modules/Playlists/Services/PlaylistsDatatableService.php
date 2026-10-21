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

namespace App\Modules\Playlists\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Services\SearchFilterParamsTrait;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Repositories\PlaylistsRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class PlaylistsDatatableService extends AbstractDatatableService
{
	use SearchFilterParamsTrait;
	private readonly PlaylistsRepository $playlistsRepository;
	private readonly AclValidator $aclValidator;
	private readonly BaseParameters $parameters;
	private readonly PlaylistUsageService $playlistUsageService;
	private readonly UserSession $userSession;

	public function __construct(PlaylistsRepository $playlistsRepository, BaseParameters $parameters, AclValidator $aclValidator, PlaylistUsageService $playlistUsageService, UserSession $userSession, LoggerInterface $logger)
	{
		$this->playlistsRepository  = $playlistsRepository;
		$this->aclValidator         = $aclValidator;
		$this->parameters           = $parameters;
		$this->playlistUsageService = $playlistUsageService;
		$this->userSession = $userSession;

		parent::__construct($logger);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function loadDatatable(): void
	{
		$UID = $this->userSession->getUID();
		if ($this->aclValidator->isModuleAdmin($UID))
		{
			$this->fetchForModuleAdmin($this->playlistsRepository, $this->parameters);
		}
		elseif ($this->aclValidator->isSubAdmin($UID))
		{
	//		$this->handleRequestSubAdmin($this->playlistsRepository);
		}
		elseif ($this->aclValidator->isEditor($UID))
		{
			// Todo
		}
		elseif ($this->aclValidator->isViewer($UID))
		{
			// Todo
		}
		else
		{
			$this->fetchForUser($this->playlistsRepository, $this->parameters, $UID);
		}
	}

	/**
	 * @param int[] $playlistIds
	 * @return array<int,bool>
	 * @throws Exception
	 */
	public function getPlaylistsInUse(array $playlistIds): array
	{
		if (empty($playlistIds))
			return [];

		return $this->playlistUsageService->determinePlaylistsInUse($playlistIds);
	}

}