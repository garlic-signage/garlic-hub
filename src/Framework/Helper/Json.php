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

namespace App\Framework\Helper;

/**
 * Encoding of data which is written into an inline <script> block of a template.
 */
class Json
{
	/**
	 * The result is a JS object literal and must be written with {{{ }}}.
	 * <, >, & and ' are hex-escaped, so a value can neither close the script tag nor start a tag.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function encodeForScript(array $data): string
	{
		$json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

		return $json === false ? '{}' : $json;
	}
}
