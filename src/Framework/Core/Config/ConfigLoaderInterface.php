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

namespace App\Framework\Core\Config;

/**
 * ConfigLoaderInterface provides a contract for loading configuration data.
 *
 * This interface is designed to enable the retrieval of configuration settings
 * for a specified module. Implementations should define how configuration data
 * is sourced, parsed, and returned.
 */
interface ConfigLoaderInterface
{
	/**
	 * @return array<string,string|array<string,string>>
	 */
	public function load(string $module): array;
}