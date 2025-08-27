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

namespace App\Modules\Users\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Users\Repositories\Edge\UserMainRepository;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;

/**
 * Service class for handling operations related to the Users datatable.
 * Extends the AbstractDatatableService to leverage common functionality.
 */
class UsersDatatableService extends AbstractDatatableService
{
	private readonly UserMainRepository $userMainRepository;
	private readonly BaseParameters $parameters;
	private readonly AclValidator $aclValidator;

	public function __construct(UserMainRepository $userMainRepository, BaseParameters $parameters, AclValidator $aclValidator, LoggerInterface $logger)
	{
		$this->userMainRepository = $userMainRepository;
		$this->aclValidator = $aclValidator;
		$this->parameters = $parameters;
		parent::__construct($logger);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function loadDatatable(): void
	{

		if ($this->aclValidator->isModuleAdmin($this->UID))
		{
			$this->fetchForModuleAdmin($this->userMainRepository, $this->parameters);
		}
		/*		elseif ($this->aclValidator->isSubAdmin($this->UID))
				{
					$this->handleRequestSubAdmin($this->userMainRepository);
				}
				elseif ($this->aclValidator->isEditor($this->UID))
				{
					// Todo
				}
				elseif ($this->aclValidator->isViewer($this->UID))
				{
					// Todo
				}
		*/
		else
		{
			$this->fetchForUser($this->userMainRepository, $this->parameters);
		}
	}

}