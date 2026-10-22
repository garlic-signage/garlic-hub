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

namespace App\Shared\User;

use App\Framework\Config\Config;

class UserEntityFactory
{
	private Config $config;

	/**
	 * @param Config $config
	 */
	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	public function create(mixed $userData): UserEntity
	{
		return match ($this->config->getEdition())
		{
			Config::PLATFORM_EDITION_ENTERPRISE => new UserEntity($userData['main'], $userData['contact'], $userData['stats'], $userData['security'], $userData['acl'], $userData['vip']),
			Config::PLATFORM_EDITION_CORE => new UserEntity($userData['main'], $userData['contact'], $userData['stats'], [], $userData['acl'], []),
			default => new UserEntity($userData['main'], [], [], [],  $userData['acl'], []),
		};
	}
}