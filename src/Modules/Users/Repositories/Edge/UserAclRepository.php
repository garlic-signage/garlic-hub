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

namespace App\Modules\Users\Repositories\Edge;

use App\Framework\Core\Acl\EditionAclModules;
use App\Framework\Core\Config\Config;
use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use App\Framework\Exceptions\UserException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

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
}