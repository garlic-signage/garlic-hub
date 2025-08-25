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

namespace App\Framework\Database\NestedSets;

use App\Framework\Exceptions\DatabaseException;

/**
 * Class Calculator provides functionality to manipulate and calculate hierarchical tree structures,
 * including determining positions and levels for nodes and performing
 * calculations necessary for subtree movement.
 */
class Calculator
{
	public const string REGION_BEFORE = 'before';
	public const string REGION_AFTER = 'after';
	public const string REGION_APPENDCHILD = 'appendChild';

	/**
	 * @param array<string,mixed> $node
	 * @throws DatabaseException
	 */
	public function determineLgtPositionByRegion(string $region, array $node): int
	{
		return match ($region)
		{
			self::REGION_BEFORE => $node['lft'],
			self::REGION_APPENDCHILD => $node['rgt'],
			self::REGION_AFTER => $node['rgt'] + 1,
			default => throw new DatabaseException('Unknown region: ' . $region),
		};
	}

	/**
	 * @param array<string,mixed> $movedNode
	 * @param array<string,mixed> $targetNode
	 * @param int $newLgtPos
	 * @param int $width
	 * @return array{distance:int, tmpPos:int, width:int}
	 */
	public function calculateBeforeMoveSubTree(array $movedNode, array $targetNode, int $newLgtPos, int $width): array
	{
		$distance = $newLgtPos - $movedNode['lft'];
		$tmpPos   = $movedNode['lft'];
		if ($distance < 0 && $movedNode['root_id'] === $targetNode['root_id'])
		{
			$distance -= $width;
			$tmpPos   += $width;
		}

		return ['distance' => $distance, 'tmpPos' => $tmpPos, 'width' => $width];
	}

	public function calculateDiffLevelByRegion(string $region, int $movedLevel, int $targetLevel): int
	{
		$diffLevel = $targetLevel - $movedLevel;

		if ($region === self::REGION_APPENDCHILD)
		{
			$diffLevel++;
		}

		return $diffLevel;
	}

	/**
	 * @param array<string,mixed> $node
	 */
	public function determineParentIdByRegion(string $region, array $node): int
	{
		if ($region !== self::REGION_APPENDCHILD)
			return $node['parent_id'];

		return $node['node_id'];
	}

}