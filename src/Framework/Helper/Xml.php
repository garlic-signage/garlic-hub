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
 * Escaping for values which are written into XML text nodes or quoted XML attributes.
 */
class Xml
{
	/**
	 * ENT_QUOTES is required: ENT_XML1 alone leaves double quotes untouched,
	 * which breaks out of a quoted attribute.
	 *
	 * Characters which XML 1.0 does not allow at all (control characters like a vertical tab
	 * from copied text) are removed, even as entity they would make the document unparsable.
	 * The escaping comes first: it substitutes invalid UTF-8, so the pattern with /u can not fail.
	 */
	public static function escape(string|int|float|null $value): string
	{
		$escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $escaped);
	}
}
