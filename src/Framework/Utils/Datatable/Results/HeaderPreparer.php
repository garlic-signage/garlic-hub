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

namespace App\Framework\Utils\Datatable\Results;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * The HeaderPreparer class is responsible for configuring header-related data,
 * including setting up language translations, managing URLs for page controls,
 * and preparing sortable headers for tables.
 * It integrates with TranslatorManager and UrlBuilder to facilitate these operations.
 */
class HeaderPreparer
{
	private BaseFilterParameters $filterParameter;
	private TranslatorManager $translatorManager;
	private UrlBuilder $urlBuilder;

	/**
	 * @param TranslatorManager $translatorManager
	 * @param UrlBuilder $urlBuilder
	 */
	public function __construct(TranslatorManager $translatorManager, UrlBuilder $urlBuilder)
	{
		$this->translatorManager = $translatorManager;
		$this->urlBuilder = $urlBuilder;
	}

	/**
	 * @param string[] $languageModules
	 */
	public function configure(BaseFilterParameters $filterParameter, string $site, array $languageModules): void
	{
		$this->filterParameter = $filterParameter;
		$this->urlBuilder->setSite($site);
		foreach ($languageModules as $module)
		{
			$this->translatorManager->addLanguageModule($module);
		}
	}

	/**
	 * @param list<HeaderField> $tableHeaderFields
	 * @return list<array<string,mixed>>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function prepareTableHeader(array $tableHeaderFields): array
	{
		$header = [];
		foreach($tableHeaderFields as $headerField)
		{
			$headerFieldName = $headerField->getName();
			$controlName     = ['CONTROL_NAME' => $headerFieldName];

			if ($headerField->isSortable())
				$controlName['if_sortable'] = $this->prepareSortableHeaderField($headerField);
			else
				$controlName['LANG_CONTROL_NAME_2'] = $this->translatorManager->translate($headerField);

			$header[] = $controlName;
		}

		return $header;
	}

	/**
	 * @param HeaderField $headerField
	 * @return array{SORTABLE_ORDER:string, SORT_CONTROL_NAME:string,LINK_CONTROL_SORT_ORDER: string, LANG_CONTROL_NAME: string}
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	protected function prepareSortableHeaderField(HeaderField $headerField):array
	{
		$sortableData = [];

		if ($this->filterParameter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN) == $headerField->getName())
		{
			if ($this->filterParameter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_ORDER) == 'asc')
			{
				$sort_order_tmp = 'desc';
				$sortableData['SORTABLE_ORDER']    = '▼';
			}
			else
			{
				$sort_order_tmp = 'asc';
				$sortableData['SORTABLE_ORDER']    = '▲';
			}
		}
		else
		{
			$sort_order_tmp = 'asc';
			$sortableData['SORTABLE_ORDER'] = '◆';
		}
		$this->filterParameter->setValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_ORDER, $sort_order_tmp);

		$this->urlBuilder
			->setPage($this->filterParameter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE))
			->setSortColumn($headerField->getName())
			->setSortOrder($sort_order_tmp)
			->setElementsPerPage($this->filterParameter->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE))
		;

		$sortableData['SORT_CONTROL_NAME']       = $headerField->getName();
		$sortableData['LINK_CONTROL_SORT_ORDER'] = $this->urlBuilder->buildFilterUrl();
		$sortableData['LANG_CONTROL_NAME']       = $this->translatorManager->translate($headerField);

		return $sortableData;
	}

}