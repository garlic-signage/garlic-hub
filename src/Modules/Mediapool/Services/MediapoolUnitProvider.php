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

namespace App\Modules\Mediapool\Services;

use App\Shared\Acl\TableUnitProvider;
use Doctrine\DBAL\ArrayParameterType;

/**
 * The directories of the mediapool. Their names repeat (every user has "Fotos"), so a directory
 * is shown with its path: "anna / Fotos / 2026".
 */
class MediapoolUnitProvider extends TableUnitProvider
{
	private const string PATH_SEPARATOR = ' / ';

	protected function describe(array $units): array
	{
		if ($units === [])
			return [];

		// the ancestors of a node in the nested set: same tree, lft and rgt around the node
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('node.node_id', 'ancestor.name')
			->from('mediapool_nodes', 'node')
			->innerJoin('node', 'mediapool_nodes', 'ancestor', 'ancestor.root_id = node.root_id AND ancestor.lft <= node.lft AND ancestor.rgt >= node.rgt')
			->where('node.node_id IN (:ids)')
			->setParameter('ids', array_column($units, 'id'), ArrayParameterType::STRING)
			->orderBy('node.node_id')
			->addOrderBy('ancestor.lft');

		$paths = [];
		foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row)
		{
			$paths[(string) $row['node_id']][] = (string) $row['name'];
		}

		foreach ($units as $key => $unit)
		{
			$units[$key]['name'] = implode(self::PATH_SEPARATOR, $paths[$unit['id']] ?? [$unit['name']]);
		}

		return $units;
	}
}
