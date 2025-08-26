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
 * Renders a hidden input field.
 *
 * This class is responsible for rendering an HTML hidden input field by implementing the FieldRenderInterface
 * and extending the AbstractInputFieldRenderer. It applies the necessary attributes to the input element
 * using the `buildAttributes` method.
 */
class HiddenRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{

	public function render(FieldInterface $field): string
	{
		$this->field = $field;
		return '<input type="hidden" '.$this->buildAttributes().'>';
	}
}