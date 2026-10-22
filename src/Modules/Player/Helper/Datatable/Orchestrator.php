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

namespace App\Modules\Player\Helper\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\Datatable\DatatableOrchestratorInterface;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Services\PlayerDatatableService;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;

/**
 * Orchestrates the player datatable: configuration, processing of user input, and preparation for rendering.
 * It coordinates the builder, preparer, and player datatable service.
 */
class Orchestrator implements DatatableOrchestratorInterface
{
	private int $UID;

	public function __construct(
		private readonly DatatableBuilder       $datatableBuilder,
		private readonly DatatablePreparer      $datatablePreparer,
		private readonly PlayerDatatableService $playerDatatableService,
		private readonly UserSession            $userSession
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
     * @param array<string,mixed> $queryParams
     * @throws CoreException
     * @throws Exception
     * @throws ModuleException
     * @throws UserException
     */
	public function processSubmittedUserInput(array $queryParams): void
	{
		$this->datatableBuilder->determineParameters($queryParams);
		$this->playerDatatableService->loadDatatable();
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
		$this->datatableBuilder->createPagination($this->playerDatatableService->getCurrentTotalResult());
		$this->datatableBuilder->createDropDown();
		$this->datatableBuilder->createTableFields();

		return $this;
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

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws DateMalformedStringException
	 */
	public function prepareUITemplate(): array
	{
		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		$pagination         = $this->datatablePreparer->preparePagination($datatableStructure['pager'], $datatableStructure['dropdown']);

		return [
			'filter_elements'     => $this->datatablePreparer->prepareFilterForm($datatableStructure['form']),
			'pagination_dropdown' => $pagination['dropdown'],
			'pagination_links'    => $pagination['links'],
			'has_add'			  => [],
			'results_header'      => $this->datatablePreparer->prepareTableHeader($datatableStructure['header'], ['player', 'main']),
			'results_list'        => $this->prepareList($datatableStructure['header']),
			'results_count'       => $this->playerDatatableService->getCurrentTotalResult(),
			'title'               => $datatableStructure['title'],
			'template_name'       => 'player/datatable',
			'module_name'		  => 'player',
			'additional_css'      => ['/css/player/datatable.css'],
			'footer_modules'      => ['/js/player/datatable/init.js'],
			'sort'				  => $this->datatablePreparer->prepareSort(),
			'page'      		  => $this->datatablePreparer->preparePage()
		];
	}


	/**
	 *
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws DateMalformedStringException
	 */
	private function prepareList(array $fields): array
	{
		return $this->datatablePreparer->prepareTableBody(
			$this->playerDatatableService->getCurrentFilterResults(),
			$fields,
			$this->UID
		);
	}

}