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
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\Datatable\AbstractDatatableOrchestrator;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Services\PlayerDatatableService;
use Doctrine\DBAL\Exception;

/**
 * Orchestrates the player datatable: configuration, processing of user input, and preparation for rendering.
 * It coordinates the builder, preparer, and player datatable service.
 *
 * @extends AbstractDatatableOrchestrator<DatatableBuilder, DatatablePreparer, PlayerDatatableService>
 */
class Orchestrator extends AbstractDatatableOrchestrator
{
	public function __construct(
		DatatableBuilder       $datatableBuilder,
		DatatablePreparer      $datatablePreparer,
		PlayerDatatableService $datatableService,
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
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function prepareContextMenu(): array
	{
		return $this->datatablePreparer->formatContextMenu();
	}

	protected function prepareModuleTemplate(): array
	{
		return [
			'has_add'        => [],
			'template_name'  => 'player/datatable',
			'module_name'    => 'player',
			'additional_css' => ['/css/player/datatable.css'],
			'footer_modules' => ['/js/player/datatable/init.js']
		];
	}
}
