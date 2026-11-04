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

namespace App\Modules\System\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Services\AbstractDatatableService;
use App\Modules\System\Helper\Audit\AuditFilterDefinition;
use App\Modules\System\Helper\Audit\Parameters;
use App\Shared\Acl\AclUnitProvider;
use App\Shared\Audit\AuditLogRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

/**
 * Loads a page of the audit log and adds the names of the users and units an entry was done to.
 */
class AuditDatatableService extends AbstractDatatableService
{
	/**
	 * @param array<string,AclUnitProvider> $unitProviders module name => provider
	 */
	public function __construct(
		private readonly AuditFilterDefinition $filterDefinition,
		DatatableQuery $datatableQuery,
		private readonly Parameters $parameters,
		private readonly AuditLogRepository $repository,
		private readonly array $unitProviders,
		LoggerInterface $logger
	)
	{
		parent::__construct($logger, $datatableQuery);
	}

	/**
	 * @throws Exception
	 */
	public function loadDatatable(): void
	{
		$this->fetchForModuleAdmin($this->filterDefinition, $this->parameters);

		$this->currentFilterResults = $this->addNames($this->currentFilterResults);
	}

	/**
	 * @param list<array<string,mixed>> $entries
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	private function addNames(array $entries): array
	{
		$targetUIDs = [];
		$unitIds    = [];
		foreach ($entries as $entry)
		{
			if (is_numeric($entry['target_UID'] ?? null))
				$targetUIDs[] = (int) $entry['target_UID'];

			if (is_string($entry['unit_id'] ?? null) && is_string($entry['module'] ?? null))
				$unitIds[$entry['module']][] = $entry['unit_id'];
		}

		$usernames = $this->repository->findUsernames(array_values(array_unique($targetUIDs)));
		$unitNames = [];
		foreach ($unitIds as $module => $ids)
		{
			if (isset($this->unitProviders[$module]))
				$unitNames[$module] = $this->unitProviders[$module]->findUnitsByIds(array_values(array_unique($ids)));
		}

		foreach ($entries as $index => $entry)
		{
			$entries[$index]['target_username'] = is_numeric($entry['target_UID'] ?? null) ? ($usernames[(int) $entry['target_UID']] ?? null) : null;
			$entries[$index]['unit_name']       = is_string($entry['unit_id'] ?? null) && is_string($entry['module'] ?? null) ? ($unitNames[$entry['module']][$entry['unit_id']]['name'] ?? null) : null;
		}

		return $entries;
	}
}
