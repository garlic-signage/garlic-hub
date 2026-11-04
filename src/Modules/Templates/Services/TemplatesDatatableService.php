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

use App\Shared\Acl\AbstractUnitDatatableService;
use App\Shared\Acl\AclSections;
use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Helper\Datatable\TemplatesFilterDefinition;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class TemplatesDatatableService extends AbstractUnitDatatableService
{
	public function __construct(private readonly TemplatesFilterDefinition $filterDefinition,
								DatatableQuery                         $datatableQuery,
								private readonly BaseParameters        $parameters,
								private readonly UserSession           $userSession,
								private readonly TemplatesUsageService $templatesUsageService,
								private readonly AclValidator          $aclValidator,
								LoggerInterface                        $logger)
	{
		parent::__construct($logger, $datatableQuery);
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
		$this->fetchForRoles($this->aclValidator, $this->filterDefinition, $this->parameters, $this->userSession->getUID(), 'template_id', AclSections::EDITOR);
	}

	/**
	 * For the selections: the units shared for using, too.
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	public function loadForSelection(): void
	{
		$this->fetchForRoles($this->aclValidator, $this->filterDefinition, $this->parameters, $this->userSession->getUID(), 'template_id', ...AclSections::UNIT_ROLES);
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