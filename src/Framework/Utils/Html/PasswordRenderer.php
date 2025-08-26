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
 * PasswordRenderer is responsible for rendering password fields with a customizable
 * layout and features, specifically designed to incorporate toggling for visibility.
 *
 * This class generates the necessary HTML markup for password input fields, enhancing
 * their functionality by integrating toggle mechanisms for showing or hiding the
 * password. It ensures accessibility by including attributes like `aria-describedby`
 * for improved user experience and support for error indications.
 */
class PasswordRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{
	public function render(FieldInterface $field): string
	{
		$this->field = $field;
		$id = $this->field->getId();

		return '<div class="password-container"><input type="password" '.$this->buildAttributes().' aria-describedby="error_'.$id.'"><span class="toggle-password bi bi-eye-fill" id="toggle_'.$id.'"></span></div>';
	}
}