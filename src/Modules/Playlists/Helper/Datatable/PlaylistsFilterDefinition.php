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

namespace App\Modules\Playlists\Helper\Datatable;

use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Database\Datatable\FilterDefinition;
use Doctrine\DBAL\ArrayParameterType;

readonly class PlaylistsFilterDefinition implements FilterDefinition
{
	private const string TABLE = 'playlists';

	public function __construct(private FilterConditions $conditions)
	{
	}

	public function table(): string
	{
		return self::TABLE;
	}

	public function joins(): array
	{
		return ['user_main' => 'user_main.UID=' . self::TABLE . '.UID'];
	}

	public function userJoins(): array
	{
		return [];
	}

	public function selects(): array
	{
		return [self::TABLE . '.*'];
	}

	public function selectsWithUser(): array
	{
		return array_merge($this->selects(), ['user_main.username', 'user_main.company_id']);
	}

	public function whereForFiltering(array $fields): array
	{
		$where = [];
		foreach ($fields as $key => $parameter)
		{
			if ($key !== 'playlist_mode')
			{
				$where = array_merge($where, $this->conditions->forField(self::TABLE, $key, $parameter));
				continue;
			}

			if (empty($parameter['value']))
				continue;

			if (str_contains($parameter['value'], ','))
				$where[$key] = $this->conditions->whereClause($parameter['value'], 'IN', 'AND', ArrayParameterType::STRING);
			else
				$where[$key] = $this->conditions->whereClause($parameter['value']);
		}
		return $where;
	}
}
