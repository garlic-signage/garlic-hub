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

namespace App\Shared\Versioning;

use Doctrine\DBAL\Exception;
use JsonException;

/**
 * The earlier states of the units of a module: before a change the module records the state it replaces.
 * Off by default (system settings): then nothing is recorded and the modules do not offer the versions.
 * A unit keeps its newest versions only, a state like the newest version is no new one.
 */
readonly class UnitVersions
{
	public function __construct(private UnitVersionsRepository $repository, private bool $enabled, private int $maxVersions) {}

	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	/**
	 * @param array<string,mixed> $snapshot what the module needs to restore the state
	 * @throws Exception
	 * @throws JsonException
	 */
	public function record(string $module, int|string $unitId, int $UID, array $snapshot): void
	{
		if (!$this->enabled)
			return;

		$unitId  = (string) $unitId;
		$json    = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		$last    = $this->repository->findLastVersion($module, $unitId);
		// e.g. a playlist exported twice without a change
		if ($last > 0 && $this->repository->findSnapshot($module, $unitId, $last) === $json)
			return;

		$version = $last + 1;
		$this->repository->insertVersion($module, $unitId, $version, $UID, $json);
		$this->repository->deleteOlderThan($module, $unitId, $version - $this->maxVersions + 1);
	}

	/**
	 * @return list<array{version:int, created_at:string, username:string}> the newest first
	 * @throws Exception
	 */
	public function findVersions(string $module, int|string $unitId): array
	{
		return $this->repository->findVersions($module, (string) $unitId);
	}

	/**
	 * @return array<string,mixed>|null null when the version does not exist
	 * @throws Exception
	 */
	public function findSnapshot(string $module, int|string $unitId, int $version): ?array
	{
		$snapshot = $this->repository->findSnapshot($module, (string) $unitId, $version);
		$decoded  = $snapshot === null ? null : json_decode($snapshot, true);

		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * The versions go with the unit, also while the versioning is off.
	 *
	 * @param list<int|string> $unitIds
	 * @throws Exception
	 */
	public function removeUnits(string $module, array $unitIds): void
	{
		if ($unitIds !== [])
			$this->repository->deleteUnits($module, array_map('strval', $unitIds));
	}
}
