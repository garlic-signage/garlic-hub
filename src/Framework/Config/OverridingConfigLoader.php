<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Config;

/**
 * The defaults of config/settings with the changes of the admin from var/settings on top.
 * Config reads through it, so every getConfigValue() sees the changed value.
 */
readonly class OverridingConfigLoader implements ConfigLoaderInterface
{
	public function __construct(
		private ConfigLoaderInterface $defaults,
		private ConfigOverrides $overrides
	) {}

	public function load(string $module): array
	{
		$config = $this->defaults->load($module);
		foreach ($this->overrides->load($module) as $section => $values)
		{
			// only sections and keys of the defaults: an override can not add settings
			if (!array_key_exists($section, $config))
				continue;

			if (is_array($values) && is_array($config[$section]))
				$config[$section] = array_replace($config[$section], array_intersect_key($values, $config[$section]));
		}

		return $config;
	}
}
