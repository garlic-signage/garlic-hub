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
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Services\SearchFilterParamsTrait;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Player\Repositories\PlayerRepository;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;

/**
 * Service class responsible for handling player data operations for a datatable.
 * Provides methods to load and manage player data based on the user's access level.
 * Extends the AbstractDatatableService to utilize common datatable functionalities.
 * Utilizes the SearchFilterParamsTrait to apply search filters.
 */
class PlayerDatatableService extends AbstractDatatableService
{
	use SearchFilterParamsTrait;
	private readonly PlayerRepository $playerRepository;
	private readonly AclValidator $aclValidator;
	private BaseParameters $parameters;

	public function __construct(PlayerRepository $playerRepository, BaseParameters $parameters, AclValidator $aclValidator, LoggerInterface $logger)
	{
		$this->playerRepository = $playerRepository;
		$this->aclValidator = $aclValidator;
		$this->parameters = $parameters;
		parent::__construct($logger);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws Exception
	 */
	public function loadDatatable(): void
	{
		if ($this->aclValidator->isModuleAdmin($this->UID))
		{
			$this->fetchForModuleAdmin($this->playerRepository, $this->parameters);
		}
		elseif ($this->aclValidator->isSubAdmin($this->UID))
		{
			//		$this->handleRequestSubAdmin($this->playerRepository);
		}
		elseif ($this->aclValidator->isEditor($this->UID))
		{
			// Todo
		}
		elseif ($this->aclValidator->isViewer($this->UID))
		{
			// Todo
		}
		else
		{
			$this->fetchForUser($this->playerRepository, $this->parameters);
		}
	}
}