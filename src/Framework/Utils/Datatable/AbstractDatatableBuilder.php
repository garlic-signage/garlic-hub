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

namespace App\Framework\Utils\Datatable;

use App\Framework\Core\Acl\AbstractAclValidator;
use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;

/**
 * Abstract class for building datatable structures.
 * Provides methods to configure and structure data tables with pagination, dropdowns, and more.
 * Requires implementation of certain methods to define table-specific behaviors.
 */
abstract class AbstractDatatableBuilder
{
	protected Translator $translator;
	protected BuildService $buildService;
	protected BaseFilterParameters $parameters;
	protected AbstractAclValidator $aclValidator;
	/** @var array<string,mixed> */
	protected array $datatableStructure = [];

	public function __construct(BuildService $buildService, BaseFilterParameters $parameters, AbstractAclValidator $aclValidator)
	{
		$this->buildService  = $buildService;
		$this->parameters    = $parameters;
		$this->aclValidator  = $aclValidator;
	}

	public function setTranslator(Translator $translator): AbstractDatatableBuilder
	{
		$this->translator = $translator;
		return $this;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getDatatableStructure(): array
	{
		return $this->datatableStructure;
	}

	abstract public function buildTitle(): void;

	/**
	 * Adds the filter parameters which are not available in the edge edition.
	 * Owner and company filters are only added for module and sub admins.
	 *
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function configureParameters(int $UID): void
	{
		if ($this->aclValidator->getConfig()->getEdition() === Config::PLATFORM_EDITION_EDGE)
			return;

		$this->addModuleParameters();

		if ($this->aclValidator->isModuleAdmin($UID) || $this->aclValidator->isSubAdmin($UID))
		{
			$this->parameters->addOwner();
			$this->parameters->addCompany();
		}
	}

	/**
	 * @param array<string,mixed> $queryParams
	 * @throws ModuleException
	 */
	public function determineParameters(array $queryParams): void
	{
		$this->parameters->setUserInputs($queryParams);
		$this->parameters->parseInputFilterAllUsers();
	}

	abstract public function collectFormElements(): void;

	abstract public function createTableFields(): static;

	/**
	 * @throws ModuleException
	 */
	public function createPagination(int $resultCount, bool $usePager = true, bool $isShortened = true): void
	{
		$pager = $this->buildService->buildPaginationLinks(
			$this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE),
			$this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE),
			$resultCount,
			$usePager,
			$isShortened);

		$this->datatableStructure['pager'] = $pager;
	}

	public function createDropDown(int $min = 10, int $max = 100, int $steps = 10): void
	{
		$this->datatableStructure['dropdown'] = $this->buildService->buildPaginationDropDown($min, $max, $steps);
	}

	/**
	 * Hook for module specific filter parameters, called by configureParameters() outside the edge edition.
	 */
	protected function addModuleParameters(): void
	{
	}
}