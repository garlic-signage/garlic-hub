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

use App\Framework\Database\BaseRepositories\FilterBase;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class TemplatesRepository extends FilterBase
{
	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'templates', 'template_id');
	}

	protected function prepareJoin(): array
	{
		return ['user_main' => 'user_main.UID=' . $this->table . '.UID'];
	}

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

	/**
	 * @return string[]
	 */
	protected function prepareSelectFilteredForUser(): array
	{
		return array_merge($this->prepareSelectFiltered(),['user_main.username', 'user_main.company_id']);
	}

	protected function prepareWhereForFiltering(array $filterFields): array
	{
		$where = [];
		foreach ($filterFields as $key => $parameter)
		{
			switch ($key)
			{
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