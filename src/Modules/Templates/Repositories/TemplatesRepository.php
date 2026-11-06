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

namespace App\Modules\Templates\Repositories;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class TemplatesRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'templates', 'template_id');
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function findFirstWithUserName(int $templateId): array
	{
		$select = [$this->table.'.*', 'user_main.username', 'user_main.company_id'];
		$join   = ['user_main' => 'user_main.UID=' . $this->table . '.UID'];
		$where  = ['template_id' => $templateId];
		return $this->getFirstDataSet($this->findAllByWithFields($select, $where, $join));
	}

	/**
	 * The keys of the installed templates of the bundled pack.
	 *
	 * @return list<string>
	 * @throws Exception
	 */
	public function findSeedKeys(): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('seed_key')->from($this->table)->where('seed_key IS NOT NULL');

		return array_values(array_filter($queryBuilder->executeQuery()->fetchFirstColumn(), is_string(...)));
	}
}
