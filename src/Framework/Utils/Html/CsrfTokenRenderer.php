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
 * A renderer for CSRF token fields that generates a hidden input element.
 *
 * This class is responsible for rendering CSRF token fields as hidden HTML input elements
 * by utilizing the attributes and properties defined in the provided field object.
 *
 * It extends AbstractInputFieldRenderer for common input rendering functionality
 * and implements the FieldRenderInterface to comply with standardized field rendering.
 *
 * @method string buildAttributes() Constructs the HTML attributes for the input element.
 */
class CsrfTokenRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{
	public function render(FieldInterface $field): string
	{
		$this->field = $field;
		return '<input type="hidden" '.$this->buildAttributes().'>';
	}
}