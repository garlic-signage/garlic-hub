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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\Html\FieldInterface;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Abstract class providing methods to prepare data for a DataTable.
 * Includes functionalities for preparing filters, pagination, headers, sorting, and other related elements.
 */
abstract class AbstractDatatablePreparer
{
	protected PrepareService $prepareService;
	protected string $moduleName;
	protected Translator $translator;
	private BaseFilterParameters $parameters;

	public function __construct(string $moduleName, PrepareService $prepareService, BaseFilterParameters $parameters)
	{
		$this->moduleName     = $moduleName;
		$this->prepareService = $prepareService;
		$this->parameters     = $parameters;
		$this->prepareService->configure($parameters, $moduleName);
	}

	public function setTranslator(Translator $translator): AbstractDatatablePreparer
	{
		$this->translator = $translator;
		return $this;
	}

	/**
	 * @param array<string,FieldInterface> $dataGridBuild
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 */
	public function prepareFilterForm(array $dataGridBuild): array
	{
		return $this->prepareService->prepareForm($dataGridBuild);
	}

	/**
	 * @param list<array{name: string, page: int, active: ?bool}> $paginationLinks
	 * @param array{min: int, max: int, steps: int} $dropDownSettings
	 * @return array{links: mixed, dropdown: mixed}
	 * @throws ModuleException
	 */
	public function preparePagination(array $paginationLinks, array $dropDownSettings): array
	{
		return $this->prepareService->preparePagination($paginationLinks, $dropDownSettings);
	}

	/**
	 * @param list<HeaderField> $fields
	 * @param string[] $langModules
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function prepareTableHeader(array $fields, array $langModules): array
	{
		return $this->prepareService->prepareDatatableHeader($fields, $langModules);
	}

	/**
	 * @return array{ADD_BI_ICON:string, LANG_ELEMENTS_ADD_LINK: string, ELEMENTS_ADD_LINK: string }
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function prepareAdd(string $iconClass = 'folder-plus', string $addLink = '#'): array
	{
		return [
			'ADD_BI_ICON' => $iconClass,
			'LANG_ELEMENTS_ADD_LINK' =>	$this->translator->translate('add', $this->moduleName),
			'ELEMENTS_ADD_LINK' => $addLink
		];
	}

	/**
	 * @return array{column: string, order:string}
	 * @throws ModuleException
	 */
	public function prepareSort(): array
	{
		return [
			'column' => $this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN),
			'order' =>  $this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_ORDER)
		];
	}

	/**
	 * @return array{current:int, num_elements: int}
	 * @throws ModuleException
	 */
	public function preparePage(): array
	{
		return [
			'current' => $this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE),
			'num_elements' => $this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE),
		];
	}

	/**
	 * @param list<array<string,mixed>> $currentFilterResults
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 */
	abstract public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array;

}