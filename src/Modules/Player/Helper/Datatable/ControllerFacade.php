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

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableFacadeInterface;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Player\Services\PlayerDatatableService;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Facade class responsible for managing the datatable's configuration, preparation, processing, and rendering.
 * It interacts with datatable components such as the builder, preparer, and specific player service in order
 * to streamline complex operations and present a cohesive interface.
 */
class ControllerFacade implements DatatableFacadeInterface
{
	private readonly DatatableBuilder $datatableBuilder;
	private readonly DatatablePreparer $datatablePreparer;
	private readonly PlayerDatatableService $playerService;
	private int $UID;

	public function __construct(DatatableBuilder $datatableBuilder, DatatablePreparer $datatablePreparer, PlayerDatatableService $playerService)
	{
		$this->datatableBuilder  = $datatableBuilder;
		$this->datatablePreparer = $datatablePreparer;
		$this->playerService  = $playerService;
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function configure(Translator $translator, Session $session): void
	{
		/** @var array{UID: string} $user */
		$user = $session->get('user');
		$this->UID = (int) $user['UID'];
		$this->playerService->setUID($this->UID);
		$this->datatableBuilder->configureParameters($this->UID);
		$this->datatablePreparer->setTranslator($translator);
		$this->datatableBuilder->setTranslator($translator);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws ModuleException
	 */
	public function processSubmittedUserInput(): void
	{
		$this->datatableBuilder->determineParameters();
		$this->playerService->loadDatatable();
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function prepareDataGrid(): static
	{
		$this->datatableBuilder->buildTitle();
		$this->datatableBuilder->collectFormElements();
		$this->datatableBuilder->createPagination($this->playerService->getCurrentTotalResult());
		$this->datatableBuilder->createDropDown();
		$this->datatableBuilder->createTableFields();

		return $this;
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function preparePlayerSettingsContextMenu(): array
	{
		return $this->datatablePreparer->formatPlayerContextMenu();
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
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
			'results_count'       => $this->playerService->getCurrentTotalResult(),
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
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException ´
	 * @throws DateMalformedStringException
	 */
	private function prepareList(array $fields): array
	{
		return $this->datatablePreparer->prepareTableBody(
			$this->playerService->getCurrentFilterResults(),
			$fields,
			$this->UID
		);
	}

}