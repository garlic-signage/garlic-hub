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

namespace App\Framework\Database\Datatable;

/**
 * Describes how the datatable of a module is queried.
 *
 * Implementations deliver plain arrays only and never touch the database,
 * the execution is done by DatatableQuery.
 */
interface FilterDefinition
{
	public function table(): string;

	/**
	 * Joins for the admin and reseller views.
	 *
	 * @return array<string, string>
	 */
	public function joins(): array;

	/**
	 * Joins for the view of a regular user.
	 *
	 * @return array<string, string>
	 */
	public function userJoins(): array;

	/**
	 * @return string[]
	 */
	public function selects(): array;

	/**
	 * Selects including the columns of the owning user.
	 *
	 * @return string[]
	 */
	public function selectsWithUser(): array;

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	public function whereForFiltering(array $fields): array;
}
