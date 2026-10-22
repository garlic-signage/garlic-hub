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

namespace App\Modules\Templates\Helper\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\Datatable\DatatableOrchestratorInterface;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Services\TemplatesDatatableService;
use Doctrine\DBAL\Exception;

/**
 * Orchestrates the templates datatable: configuration, processing of user input, and preparation for rendering.
 * It coordinates the builder, preparer, and templates datatable service.
 */
class Orchestrator implements DatatableOrchestratorInterface
{
	private int $UID;

	public function __construct(
		private readonly DatatableBuilder          $datatableBuilder,
		private readonly DatatablePreparer         $datatablePreparer,
		private readonly TemplatesDatatableService $templatesService,
		private readonly UserSession               $userSession
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function configure(): void
	{
		$this->UID = $this->userSession->getUID();
		$this->datatableBuilder->configureParameters($this->UID);
	}

    /**
     * @throws CoreException
     * @throws Exception
     */
    public function checkRights(): bool
	{
		return $this->templatesService->checkDisplayRights();
	}

    /**
     * @param array<string,mixed> $queryParams
     * @throws CoreException
     * @throws Exception
     * @throws ModuleException
     */
	public function processSubmittedUserInput(array $queryParams): void
	{
		$this->datatableBuilder->determineParameters($queryParams);
		$this->templatesService->loadDatatable();
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function prepareDataGrid(): static
	{
		$this->datatableBuilder->buildTitle();
		$this->datatableBuilder->collectFormElements();
		$this->datatableBuilder->createPagination($this->templatesService->getCurrentTotalResult());
		$this->datatableBuilder->createDropDown();
		$this->datatableBuilder->createTableFields();

		return $this;
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function prepareUITemplate(): array
	{
		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		$pagination         = $this->datatablePreparer->preparePagination($datatableStructure['pager'], $datatableStructure['dropdown']);

		return [
			'filter_elements'     => $this->datatablePreparer->prepareFilterForm($datatableStructure['form']),
			'pagination_dropdown' => $pagination['dropdown'],
			'pagination_links'    => $pagination['links'],
			'has_add'			  => $this->datatablePreparer->prepareAdd('file-plus', 'templates/settings/canvas'),
			'results_header'      => $this->datatablePreparer->prepareTableHeader($datatableStructure['header'], ['templates', 'main']),
			'results_list'        => $this->prepareList($datatableStructure['header']),
			'results_count'       => $this->templatesService->getCurrentTotalResult(),
			'title'               => $datatableStructure['title'],
			'template_name'       => 'templates/datatable',
			'module_name'		  => 'templates',
			'additional_css'      => ['/css/templates/datatable.css'],
			'footer_modules'      => ['/js/templates/datatable/init.js'],
			'sort'				  => $this->datatablePreparer->prepareSort(),
			'page'      		  => $this->datatablePreparer->preparePage()
		];
	}


	/**
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	private function prepareList(array $fields): array
	{
		/** @var list<array{"UID": int, "company_id": int, template_id:int, type:string, name:string, username:string, ...}> $currentResults */
		$currentResults = $this->templatesService->getCurrentFilterResults();
		$showedIds = array_column($currentResults, 'template_id');

		$used = $this->templatesService->getTemplatesInUse($showedIds);
		$this->datatablePreparer->setUsedTemplates($used);

		return $this->datatablePreparer->prepareTableBody($currentResults, $fields, $this->UID);
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function prepareContextMenu(): array
	{
		return $this->datatablePreparer->formatContextMenu();
	}
}