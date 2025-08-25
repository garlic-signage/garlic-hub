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


namespace App\Framework\Core\Acl;

use App\Framework\Core\Config\Config;

/**
 * A class that provides access control list (ACL) modules based on platform editions.
 *
 * The class defines sets of modules specific to each platform edition and allows retrieving
 * the relevant modules for a given edition.
 */
class EditionAclModules
{
	private const array EDGE_MODULES = ['users', 'mediapool', 'player', 'playlists'];
	private const array CORE_MODULES = ['users', 'mediapool', 'player', 'playlists'];
	private const array ENTERPRISE_MODULES = ['users', 'mediapool', 'player', 'playlists'];

	/**
	 * @return string[]
	 */
	public static function getModules(string $edition): array
	{
		return match ($edition)
		{
			Config::PLATFORM_EDITION_EDGE => self::EDGE_MODULES,
			Config::PLATFORM_EDITION_CORE => self::CORE_MODULES,
			Config::PLATFORM_EDITION_ENTERPRISE => self::ENTERPRISE_MODULES,
			default => [],
		};

	}
}