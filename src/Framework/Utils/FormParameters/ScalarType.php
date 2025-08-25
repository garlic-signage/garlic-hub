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

namespace App\Framework\Utils\FormParameters;

/**
 * Enum ScalarType
 *
 * Represents various scalar and structured data types that can be used
 * to provide type-safe constants for handling and processing specific data formats.
 */
enum ScalarType: int
{
	case INT = 1;
	case STRING = 2;
	case NUMERIC_ARRAY = 3;
	case HTML_STRING = 4;
	case FLOAT = 5;
	case BOOLEAN = 6;
	case JSON = 7;
	case JSON_HTML = 8;
	case STRING_ARRAY = 9;
	case MEDIAPOOL_FILE = 10;
}