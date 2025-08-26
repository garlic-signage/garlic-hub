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

namespace App\Framework\Utils\Html;

/**
 * Enumeration of field types used for defining various input or data handling methods.
 *
 * Each case represents a specific type of field that can be used in forms, validation,
 * or other input mechanisms. The corresponding string values define the internal representation
 * for the respective field type.
 */
enum FieldType: string
{
	case TEXT         = 'text';
	case AUTOCOMPLETE = 'autocomplete';
	case NUMBER       = 'number';
	case DROPDOWN     = 'dropdown';
	case CHECKBOX     = 'checkbox';
	case URL          = 'url';
	case DATE     = 'date';
	case PASSWORD = 'password';
	case EMAIL    = 'email';
	case CSRF     = 'csrf';
	case CLIPBOARD_TEXT = 'clipboard_text';
	case HIDDEN   = 'hidden';
}