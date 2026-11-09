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

namespace App\Shared\Acl;

use Doctrine\DBAL\Exception;

/**
 * Every module with units (player, playlists, templates ...) gives the AclGrantService
 * access to its units with one of these. The service needs the owner and the company
 * of a unit to decide who may grant rights on it, and the names for the rights page.
 */
interface AclUnitProvider
{
	/**
	 * The id comes back as the table stores it: '05' finds unit 5, its rights belong to '5'.
	 *
	 * @return array{id:string, UID:int, company_id:int}|array{} the id, the owner and his company, empty when the unit does not exist
	 * @throws Exception
	 */
	public function findUnit(int|string $unitId): array;

	/**
	 * The units whose name contains the search, for the rights page of a user.
	 *
	 * @return list<array{id:string, name:string, company_id:int}>
	 * @throws Exception
	 */
	public function searchUnits(string $search, int $limit): array;

	/**
	 * @param list<string> $unitIds
	 * @return array<string,array{name:string, company_id:int}> unit id => name and company, unknown ids are missing
	 * @throws Exception
	 */
	public function findUnitsByIds(array $unitIds): array;
}
