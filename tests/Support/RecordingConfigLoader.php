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

namespace Tests\Support;

use App\Framework\Config\ConfigLoaderInterface;
use App\Framework\Exceptions\CoreException;

/**
 * In-memory loader for tests: returns the given data per module and remembers which modules were loaded.
 */
class RecordingConfigLoader implements ConfigLoaderInterface
{
	/** @var list<string> modules in the order they were loaded */
	public array $loaded = [];

	/**
	 * @param array<string,array<string,string|array<string,string>>> $data
	 */
	public function __construct(private readonly array $data) {}

	public function load(string $module): array
	{
		$this->loaded[] = $module;
		if (!array_key_exists($module, $this->data))
			throw new CoreException('Unable to access configuration file for ' . $module);

		return $this->data[$module];
	}
}
