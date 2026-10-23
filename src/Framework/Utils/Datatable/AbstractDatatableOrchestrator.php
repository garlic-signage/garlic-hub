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
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Utils\Datatable\Results\HeaderField;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;

/**
 * Base for the module datatable orchestrators: processes the user input and prepares the data grid
 * and the UI template. Modules provide configure() (UID from the user session), their template
 * settings and, if needed, their own prepareList().
 *
 * @template TBuilder of AbstractDatatableBuilder
 * @template TPreparer of AbstractDatatablePreparer
 * @template TService of AbstractDatatableService
 */
abstract class AbstractDatatableOrchestrator implements DatatableOrchestratorInterface
{
	protected int $UID;

	/**
	 * @param TBuilder  $datatableBuilder
	 * @param TPreparer $datatablePreparer
	 * @param TService  $datatableService
	 */
	public function __construct(
		protected readonly AbstractDatatableBuilder  $datatableBuilder,
		protected readonly AbstractDatatablePreparer $datatablePreparer,
		protected readonly AbstractDatatableService  $datatableService
	) {}

	/**
	 * @param array<string,mixed> $queryParams
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function processSubmittedUserInput(array $queryParams): void
	{
		$this->datatableBuilder->determineParameters($queryParams);
		$this->datatableService->loadDatatable();
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function prepareDataGrid(): static
	{
		$this->datatableBuilder->buildTitle();
		$this->datatableBuilder->collectFormElements();
		$this->datatableBuilder->createPagination($this->datatableService->getCurrentTotalResult());
		$this->datatableBuilder->createDropDown();

		return $this;
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function prepareUITemplate(): array
	{
		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		$pagination         = $this->datatablePreparer->preparePagination($datatableStructure['pager'], $datatableStructure['dropdown']);
		$moduleTemplate     = $this->prepareModuleTemplate();

		return [
			'filter_elements'     => $this->datatablePreparer->prepareFilterForm($datatableStructure['form']),
			'pagination_dropdown' => $pagination['dropdown'],
			'pagination_links'    => $pagination['links'],
			'has_add'			  => $moduleTemplate['has_add'],
			'results_header'      => $this->datatablePreparer->prepareTableHeader($datatableStructure['header'], [$moduleTemplate['module_name'], 'main']),
			'results_list'        => $this->prepareList($datatableStructure['header']),
			'results_count'       => $this->datatableService->getCurrentTotalResult(),
			'title'               => $datatableStructure['title'],
			'template_name'       => $moduleTemplate['template_name'],
			'module_name'		  => $moduleTemplate['module_name'],
			'additional_css'      => $moduleTemplate['additional_css'],
			'footer_modules'      => $moduleTemplate['footer_modules'],
			'sort'				  => $this->datatablePreparer->prepareSort(),
			'page'      		  => $this->datatablePreparer->preparePage()
		];
	}

	/**
	 * Module specific parts of the UI template.
	 *
	 * @return array{has_add: array<string,string>, template_name: string, module_name: string, additional_css: list<string>, footer_modules: list<string>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	abstract protected function prepareModuleTemplate(): array;

	/**
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	protected function prepareList(array $fields): array
	{
		return $this->datatablePreparer->prepareTableBody(
			$this->datatableService->getCurrentFilterResults(),
			$fields,
			$this->UID
		);
	}
}
