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

namespace App\Modules\Users\Helper\Datatable;

use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Database\Datatable\FilterDefinition;

readonly class UsersFilterDefinition implements FilterDefinition
{
	private const string TABLE = 'user_main';

	public function __construct(private FilterConditions $conditions)
	{
	}

	public function table(): string
	{
		return self::TABLE;
	}

	/**
	 * user_main is the base table, so no join is needed.
	 */
	public function joins(): array
	{
		return [];
	}

	public function userJoins(): array
	{
		return [];
	}

	/**
	 * No * as this would transfer user-sensitive data like password and session_id.
	 */
	public function selects(): array
	{
		return [
			self::TABLE.'.UID', self::TABLE.'.company_id', self::TABLE.'.username', self::TABLE.'.email',
			self::TABLE.'.status', self::TABLE.'.created_at', self::TABLE.'.last_access'
		];
	}

	public function selectsWithUser(): array
	{
		return $this->selects();
	}

	public function whereForFiltering(array $fields): array
	{
		$where = [];
		foreach ($fields as $key => $parameter)
		{
			switch ($key)
			{
				case Parameters::PARAMETER_FROM_STATUS:
					$where[self::TABLE.'.status'] = $this->conditions->whereClause((int) $parameter['value'], '>=');
					break;

				default:
					$where = array_merge($where, $this->conditions->forField(self::TABLE, $key, $parameter));
			}
		}
		return $where;
	}
}
