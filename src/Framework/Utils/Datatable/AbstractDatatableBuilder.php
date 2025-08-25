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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;

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
	/** @var array<string,mixed> */
	protected array $datatableStructure = [];

	public function __construct(BuildService $buildService, BaseFilterParameters $parameters)
	{
		$this->buildService  = $buildService;
		$this->parameters    = $parameters;
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

	abstract public function configureParameters(int $UID): void;

	abstract public function determineParameters(): void;

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
}