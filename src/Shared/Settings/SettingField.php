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

namespace App\Shared\Settings;

/**
 * One setting an admin may change: a key in a section of the ini of the module.
 * The label is a key of the translations of the module. A select offers only its options.
 * A number with a factor is shown in its unit (a factor of 1048576: MB) and stored multiplied, min and max are in the unit.
 */
final readonly class SettingField
{
	/**
	 * @param list<string> $options the values of a select
	 */
	public function __construct(
		public string $section,
		public string $key,
		public SettingType $type,
		public string $label,
		public ?int $min = null,
		public ?int $max = null,
		public array $options = [],
		public int $factor = 1
	) {}
}
