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

namespace App\Shared\User\Repositories\Core;

use App\Shared\Acl\EditionAclModules;
use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use App\Framework\Exceptions\UserException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;

/**
 * Repository class for managing user ACLs (Access Control Lists).
 * Extends the base functionality provided by SqlBase and incorporates additional traits for CRUD
 * operations and specific data retrieval functionalities.
 */
class UserAclRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	const string USER_MODULE_ADMIN = 'module_admin';
	const string USER_SUB_ADMIN = 'sub_admin';
	const string USER_EDITOR = 'editor';
	const string USER_VIEWER = 'viewer';

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'user_acl', 'UID');
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function addAdminRights(Config $config, int $UID = 1): void
	{
		$modules = EditionAclModules::getModules($config->getEdition());

		$this->connection->executeStatement('PRAGMA foreign_keys = OFF;');
	//	$this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');

		foreach ($modules as $module)
		{
			$adminRights = (int) $config->getConfigValue('moduleadmin', $module,'GlobalACLs');
			$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES ($UID, $adminRights, '$module');");
		}
		$this->connection->executeStatement('PRAGMA foreign_keys = ON;');
	}

	/**
	 * The bits of the user in the module, 0 without a row.
	 *
	 * @throws Exception
	 */
	public function findAcl(int $UID, string $module): int
	{
		return (int) $this->findOneValueBy('acl', ['UID' => $UID, 'module' => $module]);
	}

	/**
	 * One row per user and module, without bits there is no row.
	 *
	 * @throws Exception
	 */
	public function saveAcl(int $UID, string $module, int $acl): void
	{
		$conditions = ['UID' => $UID, 'module' => $module];

		if ($acl === 0)
			$this->deleteBy($conditions);
		elseif ($this->findOneValueBy('UID', $conditions) === '')
			$this->insert(['UID' => $UID, 'module' => $module, 'acl' => $acl]);
		else
			$this->updateWithWhere(['acl' => $acl], $conditions);
	}

	/**
	 * @return list<array{UID:int, username:string, company_id:int}>
	 * @throws Exception
	 */
	public function findUsersWithBit(string $module, int $bit): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('user_main.UID', 'user_main.username', 'user_main.company_id')
			->from($this->table)
			->innerJoin($this->table, 'user_main', 'user_main', 'user_main.UID = ' . $this->table . '.UID')
			->where($this->table . '.module = :module')
			->andWhere('(' . $this->table . '.acl & :bit) > 0')
			->setParameter('module', $module)
			->setParameter('bit', $bit, ParameterType::INTEGER)
			->orderBy('user_main.username');

		$users = [];
		foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row)
		{
			$users[] = ['UID' => (int) $row['UID'], 'username' => (string) $row['username'], 'company_id' => (int) $row['company_id']];
		}

		return $users;
	}
}
