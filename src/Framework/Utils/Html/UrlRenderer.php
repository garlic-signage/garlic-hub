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
 * Renders a URL input field based on the provided field data.
 *
 * This method generates an HTML input element of type "text" with attributes
 * such as pattern, placeholder, and aria-describedby dynamically populated from
 * the provided field object. If the provided field is not an instance of
 * UrlField, the method returns an empty string.
 *
 * @param UrlField|FieldInterface $field The field object to render.
 *
 * @return string The rendered HTML string for the input field or an empty string
 *                if the field is not an instance of UrlField.
 */
class UrlRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{
	public function render(UrlField|FieldInterface $field): string
	{
		$this->field = $field;
		if (!($this->field instanceof UrlField))
			return '';

		return '<input type="text" '.$this->buildAttributes().' pattern="'.$this->field->getPattern().'" placeholder="'.$this->field->getPlaceholder().'" aria-describedby="error_'.$this->field->getId().'">';

	}
}