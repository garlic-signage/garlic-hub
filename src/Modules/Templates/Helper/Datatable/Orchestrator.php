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
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\Datatable\AbstractDatatableOrchestrator;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Services\TemplatesDatatableService;
use Doctrine\DBAL\Exception;

/**
 * Orchestrates the templates datatable: configuration, processing of user input, and preparation for rendering.
 * It coordinates the builder, preparer, and templates datatable service.
 *
 * @extends AbstractDatatableOrchestrator<DatatableBuilder, DatatablePreparer, TemplatesDatatableService>
 */
class Orchestrator extends AbstractDatatableOrchestrator
{
	public function __construct(
		DatatableBuilder          $datatableBuilder,
		DatatablePreparer         $datatablePreparer,
		TemplatesDatatableService $datatableService,
		private readonly UserSession $userSession
	)
	{
		parent::__construct($datatableBuilder, $datatablePreparer, $datatableService);
	}

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
		return $this->datatableService->checkDisplayRights();
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
	 */
	protected function prepareModuleTemplate(): array
	{
		return [
			'has_add'        => $this->datatablePreparer->prepareAdd('file-plus', 'templates/settings/canvas'),
			'template_name'  => 'templates/datatable',
			'module_name'    => 'templates',
			'additional_css' => ['/css/templates/datatable.css'],
			'footer_modules' => ['/js/templates/datatable/init.js']
		];
	}

	/**
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	protected function prepareList(array $fields): array
	{
		/** @var list<array{"UID": int, "company_id": int, template_id:int, type:string, name:string, username:string, ...}> $currentResults */
		$currentResults = $this->datatableService->getCurrentFilterResults();
		$showedIds = array_column($currentResults, 'template_id');

		$used = $this->datatableService->getTemplatesInUse($showedIds);
		$this->datatablePreparer->setUsedTemplates($used);

		return $this->datatablePreparer->prepareTableBody($currentResults, $fields, $this->UID);
	}
}
