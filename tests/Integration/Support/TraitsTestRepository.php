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

namespace Tests\Integration\Support;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;

/**
 * A repository which uses only the shared traits, on the table templates. It also makes the
 * protected helpers of the traits callable for the tests.
 */
class TraitsTestRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'templates', 'template_id');
	}

	/**
	 * @return string[]
	 */
	public function explode(string $data): array
	{
		return $this->secureExplode($data);
	}

	/**
	 * @return array<mixed>
	 */
	public function unserialize(?string $data): array
	{
		return $this->secureUnserialize($data);
	}

	/**
	 * @param array<mixed> $data
	 */
	public function implode(array $data): string
	{
		return $this->secureImplode($data);
	}

	/**
	 * @param array<mixed> $data
	 */
	public function serialize(array $data): string
	{
		return $this->secureSerialize($data);
	}
}
