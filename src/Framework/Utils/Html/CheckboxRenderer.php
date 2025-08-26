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
 * Renders an HTML checkbox input field based on the provided field object.
 * Ensures the correct state and data are applied to the checkbox field during rendering.
 */
class CheckboxRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{
	public function render(CheckboxField|FieldInterface $field): string
	{
		$this->field = $field;
		if (!($this->field instanceof CheckboxField))
			return '';

		$id      = $this->field->getId();
		$checked = $this->field->isChecked() ? 'checked' : '';

		return '<input type="checkbox" id="'.$id.'" name="'.$id.'" '.$checked.' value="1" aria-describedby="error_'.$id.'"> '.$this->field->getTitle();

	}
}