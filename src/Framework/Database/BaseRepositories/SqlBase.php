<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Database\BaseRepositories;

use Doctrine\DBAL\Connection;

/**
 * Abstract class Base
 *
 * Provides a base model for database operations.
 */
abstract class SqlBase
{
	protected string $table;
	protected string $idField;
	protected readonly Connection $connection;

	public function __construct(Connection $connection, string $table, string $idField)
	{
		$this->connection   = $connection;
		$this->table        = $table;
		$this->idField      = $idField;
	}

	public function getTable(): string
	{
		return $this->table;
	}

	public function getIdField(): string
	{
		return $this->idField;
	}

	public function setTable(string $table): SqlBase
	{
		$this->table = $table;
		return $this;
	}

	public function setIdField(string $idField): SqlBase
	{
		$this->idField = $idField;
		return $this;
	}



}