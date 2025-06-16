<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace App\Modules\Users\Repositories\Edge;

use App\Framework\Database\BaseRepositories\FilterBase;
use App\Modules\Users\Helper\Datatable\Parameters;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Provides user data handling for authentication.
 */
class UserMainRepository extends FilterBase
{
	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'user_main', 'UID');
	}

	/**
	 * We do not want to use * as this will transfer user sensitive data
	 * like passwords, tokens etc.
	 *
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findById(int|string $id): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('UID, company_id, status, locale, email, username')
			->from($this->table)
			->where($this->idField . ' = :id')
			->setParameter('id', $id);

		return $queryBuilder->executeQuery()->fetchAssociative();
	}

	/**
	 * @param string $identifier
	 *
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function findByIdentifier(string $identifier): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('UID, password, locale, status, company_id')->from($this->table);

		if (filter_var($identifier, FILTER_VALIDATE_EMAIL))
			$queryBuilder->where('email = :identifier');
		else
			$queryBuilder->where('username = :identifier');

		$queryBuilder->setParameter('identifier', $identifier);

		return $this->fetchAssociative($queryBuilder);
	}

	/**
	 * @return array<string, string>
	 */
	protected function prepareJoin(): array
	{
		return [];
	}

	/**
	 * @return array<string, string>
	 */
	protected function prepareUserJoin(): array
	{
		return [];
	}

	/**
	 * @return string[]
	 */
	protected function prepareSelectFiltered(): array
	{
		return [$this->table.'.*'];
	}

	protected function prepareSelectFilteredForUser(): array
	{
		return $this->prepareSelectFiltered();
	}

	/**
	 * @param array<string,mixed> $filterFields
	 * @return array<string,mixed>
	 */
	protected function prepareWhereForFiltering(array $filterFields): array
	{
		$where = [];
		foreach ($filterFields as $key => $parameter)
		{
			switch ($key)
			{
				case Parameters::PARAMETER_FROM_STATUS:
					$where['status'] = $this->generateWhereClause($parameter['value'], '>=');
					break;
				default:
					$clause = $this->determineWhereForFiltering($key, $parameter);
					if (!empty($clause))
					{
						$where = array_merge($where, $clause);
					}
			}
		}
		return $where;
	}
}
