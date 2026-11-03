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

use App\Shared\Acl\AbstractUnitDatatableService;
use App\Shared\Acl\AclSections;
use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Framework\Services\SearchFilterParamsTrait;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Datatable\PlaylistsFilterDefinition;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class PlaylistsDatatableService extends AbstractUnitDatatableService
{
	use SearchFilterParamsTrait;
	private readonly PlaylistsFilterDefinition $filterDefinition;
	private readonly AclValidator $aclValidator;
	private readonly BaseParameters $parameters;
	private readonly PlaylistUsageService $playlistUsageService;
	private readonly UserSession $userSession;

	public function __construct(PlaylistsFilterDefinition $filterDefinition, DatatableQuery $datatableQuery, BaseParameters $parameters, AclValidator $aclValidator, PlaylistUsageService $playlistUsageService, UserSession $userSession, LoggerInterface $logger)
	{
		$this->filterDefinition     = $filterDefinition;
		$this->aclValidator         = $aclValidator;
		$this->parameters           = $parameters;
		$this->playlistUsageService = $playlistUsageService;
		$this->userSession = $userSession;

		parent::__construct($logger, $datatableQuery);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function loadDatatable(): void
	{
		$this->fetchForRoles($this->aclValidator, $this->filterDefinition, $this->parameters, $this->userSession->getUID(), 'playlist_id', AclSections::EDITOR);
	}

	/**
	 * For the selections: the units shared for using, too.
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	public function loadForSelection(): void
	{
		$this->fetchForRoles($this->aclValidator, $this->filterDefinition, $this->parameters, $this->userSession->getUID(), 'playlist_id', AclSections::EDITOR, AclSections::VIEWER);
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