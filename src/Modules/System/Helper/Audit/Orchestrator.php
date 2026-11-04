<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\System\Helper\Audit;

use App\Framework\Utils\Datatable\AbstractDatatableOrchestrator;
use App\Modules\Auth\UserSession;
use App\Modules\System\Services\AuditDatatableService;

/**
 * The list of the audit log: no owner or company filter, the module admin of system sees everything.
 *
 * @extends AbstractDatatableOrchestrator<DatatableBuilder, DatatablePreparer, AuditDatatableService>
 */
class Orchestrator extends AbstractDatatableOrchestrator
{
	public function __construct(
		DatatableBuilder $datatableBuilder,
		DatatablePreparer $datatablePreparer,
		AuditDatatableService $datatableService,
		private readonly UserSession $userSession
	)
	{
		parent::__construct($datatableBuilder, $datatablePreparer, $datatableService);
	}

	public function configure(): void
	{
		$this->UID = $this->userSession->getUID();
	}

	protected function prepareModuleTemplate(): array
	{
		return [
			'has_add'        => [],
			'template_name'  => 'system/audit',
			'module_name'    => 'system',
			'additional_css' => ['/css/system/audit.css'],
			'footer_modules' => []
		];
	}
}
