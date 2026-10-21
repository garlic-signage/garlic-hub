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


namespace App\Modules\Templates\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Repositories\TemplatesRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class TemplatesDatatableService extends AbstractDatatableService
{
	public function __construct(private readonly TemplatesRepository   $templatesRepository,
								private readonly BaseParameters        $parameters,
								private readonly UserSession           $userSession,
								private readonly TemplatesUsageService $templatesUsageService,
								private readonly AclValidator          $aclValidator,
								LoggerInterface                        $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function checkDisplayRights(): bool
	{
		if ($this->aclValidator->isSimpleAdmin($this->userSession->getUID()))
			return true;

		return false;
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
			$this->fetchForModuleAdmin($this->templatesRepository, $this->parameters);
		}
		elseif ($this->aclValidator->isSubAdmin($UID))
		{
			//		$this->handleRequestSubAdmin($this->templatesRepository);
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
			$this->fetchForUser($this->templatesRepository, $this->parameters, $UID);
		}
	}

	/**
	 * @param int[] $templateIds
	 * @return array<int,int>
	 * @throws Exception
	 */
	public function getTemplatesInUse(array $templateIds): array
	{
		if ($templateIds === [])
			return [];

		return $this->templatesUsageService->determineTemplatesInUse($templateIds);
	}
}