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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FormBuilder;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class PrepareService
 *
 * Provides functionality for configuring and preparing various components
 * such as forms, pagination, and datatable headers. It integrates multiple
 * preparer classes to ensure proper setup and formatting of required data structures.
 */
class PrepareService
{
	private HeaderPreparer $headerPreparer;
	private BodyPreparer $bodyPreparer;
	private Preparer $paginationPreparer;
	private FormBuilder $formBuilder;
	private string $moduleName;

	private BaseFilterParameters $parameters;

	/**
	 * @param HeaderPreparer $headerFormatter
	 * @param BodyPreparer $bodyFormatter
	 * @param Preparer $paginationFormatter
	 * @param FormBuilder $formBuilder
	 */
	public function __construct(FormBuilder $formBuilder, Preparer $paginationFormatter, HeaderPreparer $headerFormatter, BodyPreparer $bodyFormatter)
	{
		$this->formBuilder         = $formBuilder;
		$this->paginationPreparer = $paginationFormatter;
		$this->headerPreparer     = $headerFormatter;
		$this->bodyPreparer       = $bodyFormatter;
	}

	/**
	 * @param array<string,FieldInterface> $datatableForm
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 */
	public function prepareForm(array $datatableForm): array
	{
		return $this->formBuilder->prepareForm($datatableForm);
	}

	public function configure(BaseFilterParameters $parameters, string $moduleName): void
	{
		$this->paginationPreparer->setSite($moduleName)->setBaseFilter($parameters);
		$this->moduleName = $moduleName;
		$this->parameters = $parameters;
	}

	/**
	 * @param list<array{name: string, page: int, active: ?bool}> $paginationLinks
	 * @param array{min: int, max: int, steps: int} $dropDownSettings
	 * @return array{links: mixed, dropdown: mixed}
	 * @throws ModuleException
	 */
	public function preparePagination(array $paginationLinks, array $dropDownSettings): array
	{
		return [
			'links' => $this->paginationPreparer->prepareLinks($paginationLinks),
			'dropdown' => $this->paginationPreparer->prepareDropdown($dropDownSettings)
			];
	}

	/**
	 * @param list<HeaderField> $fields
	 * @param string[] $langModules
	 * @return list<array<string,mixed>>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function prepareDatatableHeader(array $fields, array $langModules):  array
	{
		$this->headerPreparer->configure($this->parameters, $this->moduleName, $langModules);
		return $this->headerPreparer->prepareTableHeader($fields);
	}

	public function getBodyPreparer(): BodyPreparer
	{
		return $this->bodyPreparer;
	}
}