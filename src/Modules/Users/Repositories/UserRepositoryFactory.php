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

namespace App\Modules\Users\Repositories;

use App\Framework\Core\Config\Config;
use App\Modules\Users\Repositories\Core\UserContactRepository;
use App\Modules\Users\Repositories\Core\UserStatsRepository;
use App\Modules\Users\Repositories\Edge\UserAclRepository;
use App\Modules\Users\Repositories\Edge\UserMainRepository;
use App\Modules\Users\Repositories\Edge\UserTokensRepository;
use App\Modules\Users\Repositories\Enterprise\UserSecurityRepository;
use App\Modules\Users\Repositories\Enterprise\UserVipRepository;
use Doctrine\DBAL\Connection;

/**
 * Factory class responsible for creating and managing user-related repository instances
 * based on the platform edition configuration.
 */
class UserRepositoryFactory
{
	private readonly Config $config;
	private Connection $connection;
	/**
	 * @var array{
	 *     main: UserMainRepository,
	 *     tokens: UserTokensRepository,
	 *     acl: UserAclRepository
	 *	 }|null
	 */
	private ?array $repositories = null;

	/**
	 * @param Config     $config
	 * @param Connection $connection
	 */
	public function __construct(Config $config, Connection $connection)
	{
		$this->config = $config;
		$this->connection = $connection;
	}

	/**
	 * @return array{
	 *     main: UserMainRepository,
	 *     tokens: UserTokensRepository,
	 *     acl: UserAclRepository,
	 *     contact?: UserContactRepository,
	 *     stats?: UserStatsRepository,
	 * 	   vip?: UserVipRepository,
	 *     security?: UserSecurityRepository
	 *	 }
	 */
	public function create(): array
	{
		if ($this->repositories !== null)
			return $this->repositories;

		$this->repositories = match ($this->config->getEdition())
		{
			Config::PLATFORM_EDITION_ENTERPRISE => [
				'main'     => new UserMainRepository($this->connection),
				'tokens'   => new UserTokensRepository($this->connection),
				'acl'      => new UserAclRepository($this->connection),
				'contact'  => new UserContactRepository($this->connection),
				'stats'    => new UserStatsRepository($this->connection),
				'vip'      => new UserVipRepository($this->connection),
				'security' => new UserSecurityRepository($this->connection)
			],
			Config::PLATFORM_EDITION_CORE => [
				'main'    => new UserMainRepository($this->connection),
				'tokens'  => new UserTokensRepository($this->connection),
				'acl'     => new UserAclRepository($this->connection),
				'contact' => new UserContactRepository($this->connection),
				'stats'   => new UserStatsRepository($this->connection)
			],
			default => [
				'main' => new UserMainRepository($this->connection),
				'tokens' => new UserTokensRepository($this->connection),
				'acl'  => new UserAclRepository($this->connection)
			],
		};
		return $this->repositories;
	}
}