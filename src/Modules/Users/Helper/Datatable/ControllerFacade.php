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

namespace App\Modules\Users\Helper\Datatable;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableFacadeInterface;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\Services\UsersDatatableService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * This class acts as a facade to handle operations related to a datatable framework.
 * It integrates multiple services and components to streamline data management
 * for users and administrators.
 */
class ControllerFacade implements DatatableFacadeInterface
{
	private readonly DatatableBuilder $datatableBuilder;
	private readonly DatatablePreparer $datatableFormatter;
	private readonly UsersDatatableService $usersService;
	private readonly UsersAdminService $usersAdminService;
	private int $UID;

	public function __construct(DatatableBuilder $datatableBuilder, DatatablePreparer $datatableFormatter, UsersDatatableService $usersService, UsersAdminService $usersAdminService)
	{
		$this->datatableBuilder   = $datatableBuilder;
		$this->datatableFormatter = $datatableFormatter;
		$this->usersService       = $usersService;
		$this->usersAdminService  = $usersAdminService;
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function configure(Translator $translator, Session $session): void
	{
		/** @var array{UID: int} $user */
		$user = $session->get('user');
		$this->UID = (int) $user['UID'];
		$this->usersService->setUID($this->UID);
		$this->usersAdminService->setUID($this->UID);
		$this->datatableBuilder->configureParameters($this->UID);
		$this->datatableFormatter->setTranslator($translator);
		$this->datatableBuilder->setTranslator($translator);
	}

	/**
	 * @throws Exception
	 */
	public function deleteUser(int $UID): bool
	{
		return $this->usersAdminService->deleteUser($UID);
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
		$this->usersService->loadDatatable();
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function prepareDataGrid(): static
	{
		$this->datatableBuilder->buildTitle();
		$this->datatableBuilder->collectFormElements();
		$this->datatableBuilder->createPagination($this->usersService->getCurrentTotalResult());
		$this->datatableBuilder->createDropDown();
		$this->datatableBuilder->createTableFields();

		return $this;
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function prepareUITemplate(): array
	{
		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		$pagination         = $this->datatableFormatter->preparePagination($datatableStructure['pager'], $datatableStructure['dropdown']);

		return [
			'filter_elements'     => $this->datatableFormatter->prepareFilterForm($datatableStructure['form']),
			'pagination_dropdown' => $pagination['dropdown'],
			'pagination_links'    => $pagination['links'],
			'has_add'			  => $this->datatableFormatter->prepareAdd('person-add', 'users/new'),
			'results_header'      => $this->datatableFormatter->prepareTableHeader($datatableStructure['header'],  ['users', 'main']),
			'results_list'        => $this->formatList($datatableStructure['header']),
			'results_count'       => $this->usersService->getCurrentTotalResult(),
			'title'               => $datatableStructure['title'],
			'template_name'       => 'users/datatable',
			'module_name'		  => 'users',
			'additional_css'      => ['/css/users/datatable.css'],
			'footer_modules'      => ['/js/users/datatable/init.js'],
			'sort'				  => $this->datatableFormatter->prepareSort(),
			'page'      		  => $this->datatableFormatter->preparePage()
		];
	}

	/**
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	private function formatList(array $fields): array
	{
		return $this->datatableFormatter->prepareTableBody(
			$this->usersService->getCurrentFilterResults(),
			$fields,
			$this->UID
		);
	}
}