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

namespace App\Modules\Users\Repositories\Enterprise;

use App\Framework\Database\BaseRepositories\SqlBase;
use Doctrine\DBAL\Connection;

/**
 * UserSecurityRepository is a class that extends SqlBase and manages database interactions
 * related to the user security table. It utilizes a connection instance for operations and
 * sets 'user_security' as the table name with 'UID' as the primary key.
 *
 */
class UserSecurityRepository extends SqlBase
{
	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'user_security', 'UID');
	}
}