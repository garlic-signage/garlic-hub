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

namespace App\Framework\Utils\Html;

/**
 * A renderer class responsible for generating the HTML for a dropdown field.
 * Extends AbstractInputFieldRenderer and implements FieldRenderInterface.
 */
class DropdownRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{

	public function render(FieldInterface $field): string
	{
		$this->field = $field;

		$id = $this->field->getId();

		if (!($this->field instanceof DropdownField))
			return '';

		$html = '<select id="'.$id.'" name= "' . $this->field->getName(). '" aria-describedby="error_' . $id. '">';
		if ($this->field->isOptionsZero())
			$html .= '<option value="">-</option>';

		foreach ($this->field->getOptions() as $key => $value)
		{
			$selected = $key == $this->field->getValue() ? ' selected' : '';
			$html .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
		}
		$html .= '</select>';

		return $html;
	}

}