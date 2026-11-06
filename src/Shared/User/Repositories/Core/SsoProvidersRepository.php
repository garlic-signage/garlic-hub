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

namespace App\Shared\User\Repositories\Core;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use App\Shared\User\Sso\SsoProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * The OpenID Connect providers of the login, each of a company.
 */
class SsoProvidersRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'sso_providers', 'provider_id');
	}

	/**
	 * @throws Exception
	 */
	public function findProvider(int $providerId): ?SsoProvider
	{
		$row = $this->findFirstById($providerId);

		return $row === [] ? null : SsoProvider::fromRow($row);
	}

	/**
	 * The buttons of the login page.
	 *
	 * @return list<SsoProvider>
	 * @throws Exception
	 */
	public function findEnabled(): array
	{
		return $this->toProviders($this->findAllBy(['enabled' => 1], [], [], '', [['sort' => 'name', 'order' => 'ASC']]));
	}

	/**
	 * @return list<SsoProvider>
	 * @throws Exception
	 */
	public function findAllOfCompany(int $companyId): array
	{
		return $this->toProviders($this->findAllBy(['company_id' => $companyId], [], [], '', [['sort' => 'name', 'order' => 'ASC']]));
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<SsoProvider>
	 */
	private function toProviders(array $rows): array
	{
		return array_values(array_filter(array_map(SsoProvider::fromRow(...), $rows)));
	}
}
