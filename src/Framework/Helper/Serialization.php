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

namespace App\Framework\Helper;

/**
 * Handles secure serialization and unserialization of data.
 */
class Serialization
{
	/**
	 * Unserializes stored arrays without creating objects, so manipulated data cannot
	 * instantiate classes (PHP object injection). Everything that is not an array
	 * results in an empty array.
	 *
	 * @return array<string, mixed>
	 */
	public static function unserializeSecure(?string $value): array
	{
		if ($value === null || $value === '')
			return [];

		$ar = @unserialize($value, ['allowed_classes' => false]);
		return is_array($ar) ? $ar : [];
	}
}