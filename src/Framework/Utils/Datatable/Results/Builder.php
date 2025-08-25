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

namespace App\Framework\Utils\Datatable\Results;

/**
 * Represents a builder for managing table header fields.
 *
 * This class is responsible for creating and storing header fields
 * with their respective configuration, such as name and sortability.
 */
class Builder
{
	/** @var list<HeaderField>  */
	private array $tableHeaderFields = [];

	/**
	 * @return list<HeaderField>
	 */
	public function getHeaderFields(): array
	{
		return $this->tableHeaderFields;
	}

	public function createField(string $fieldName, bool $sortable): void
	{
		$this->tableHeaderFields[] = $this->createHeaderField()->setName($fieldName)->sortable($sortable);
	}

	private function createHeaderField(): HeaderField
	{
		return new HeaderField();
	}
}