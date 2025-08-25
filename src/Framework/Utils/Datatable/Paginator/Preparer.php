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

namespace App\Framework\Utils\Datatable\Paginator;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;

/**
 * Class responsible for preparing URLs and UI components such as pagination links and dropdown settings
 * based on filtering and sorting parameters.
 */
class Preparer
{
	private BaseFilterParameters $baseFilter;
	private UrlBuilder $urlBuilder;

	/**
	 * @param UrlBuilder $urlBuilder
	 */
	public function __construct(UrlBuilder $urlBuilder)
	{
		$this->urlBuilder = $urlBuilder;
	}

	public function setBaseFilter(BaseFilterParameters $baseFilter): static
	{
		$this->baseFilter = $baseFilter;
		return $this;
	}

	public function setSite(string $site): static
	{
		$this->urlBuilder->setSite($site);
		return $this;
	}

	/**
	 * @param list<array{name: string, page: int, active: ?bool}> $pageLinks
	 * @return list<array<string,mixed>>
	 * @throws ModuleException
	 */
	public function prepareLinks(array $pageLinks): array
	{
		$this->urlBuilder
			->setSortColumn($this->baseFilter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN))
			->setSortOrder($this->baseFilter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_ORDER))
			->setElementsPerPage($this->baseFilter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE))
		;

		$data = [];
		foreach($pageLinks as $values)
		{
			$this->urlBuilder->setPage($values['page']);

			$data[] = [
				'ELEMENTS_PAGELINK'   => $this->urlBuilder->buildFilterUrl(),
				'ELEMENTS_PAGENAME'   => $values['name'],
				'ELEMENTS_PAGENUMBER' => $values['page'],
				'ELEMENTS_ACTIVE_PAGE' => isset($values['active']) ? 'active_page' : ''
			];
		}

		return $data;
	}

	/**
	 * @param array{min: int, max: int, steps: int} $dropDownSettings
	 * @return list<array<string,mixed>>
	 * @throws ModuleException
	 */
	public function prepareDropdown(array $dropDownSettings): array
	{
		$this->urlBuilder
			->setSortColumn($this->baseFilter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN))
			->setSortOrder($this->baseFilter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_ORDER))
			->setPage($this->baseFilter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE))
		;

		$data = [];
		$currentElementsPerPage = (int) $this->baseFilter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE);
		for ($i = $dropDownSettings['min']; $i <= $dropDownSettings['max']; $i += $dropDownSettings['steps'])
		{
			$data[] = [
				'ELEMENTS_PER_PAGE_VALUE' => $i,
				'ELEMENTS_PER_PAGE_DATA_LINK' => $this->urlBuilder->buildFilterUrl(),
				'ELEMENTS_PER_PAGE_NAME' => $i,
				'ELEMENTS_PER_PAGE_SELECTED' => ($i === $currentElementsPerPage) ? 'selected' : ''
			];
		}
		return $data;
	}
}