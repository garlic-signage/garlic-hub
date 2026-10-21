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

namespace App\Framework\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Database\Datatable\FilterDefinition;
use App\Framework\Utils\FormParameters\BaseParameters;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for managing datatables in different contexts.
 * Provides base functionality for fetching and handling data
 * with filters and parameters for both module administrators and users.
 */
abstract class AbstractDatatableService extends AbstractBaseService
{
	use SearchFilterParamsTrait;

	public function __construct(LoggerInterface $logger, private readonly DatatableQuery $datatableQuery)
	{
		parent::__construct($logger);
	}

	abstract public function loadDatatable(): void;

	/**
	 * @throws Exception
	 */
	public function fetchForModuleAdmin(FilterDefinition $definition, BaseParameters $parameters): static
	{
		$fields = $parameters->getInputParametersArray();

		return $this->setAllResultData(
			$this->datatableQuery->countForModuleAdmin($definition, $fields),
			$this->datatableQuery->findForModuleAdmin($definition, $fields)
		);
	}

	/**
	 * @throws Exception
	 */
	public function fetchForUser(FilterDefinition $definition, BaseParameters $parameters, int $UID): static
	{
		$fields = $parameters->getInputParametersArray();

		return $this->setAllResultData(
			$this->datatableQuery->countForUser($definition, $fields, $UID),
			$this->datatableQuery->findForUser($definition, $fields, $UID)
		);
	}
}
