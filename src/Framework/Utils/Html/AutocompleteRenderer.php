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
 * Renders an autocomplete input field with an associated datalist for suggestions.
 *
 * This class is used to generate the HTML structure required for rendering
 * an autocomplete input field, handling the necessary attributes and relation
 * to its hidden input and datalist elements. It ensures the field is properly
 * identified and labeled.
 *
 * Implements:
 * - FieldRenderInterface: Ensures compatibility with the field rendering system.
 *
 * Extends:
 * - AbstractInputFieldRenderer: Provides base functionality for rendering input fields.
 *
 * Methods:
 * - render(FieldInterface $field): Generates the HTML for an autocomplete field,
 *   linking an input element, its hidden counterpart, and a datalist for suggestions.
 */
class AutocompleteRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{

	public function render(FieldInterface $field): string
	{
		$this->field = $field;
		$inputId    = $this->field->getId().'_search';
		$datalistId = $this->field->getId().'_suggestions';
		if (!($this->field instanceof AutocompleteField))
			return '';

		return '<input id="'.$inputId.'" list="'.$datalistId.'" value="'.$this->field->getDataLabel().'" data-id="'.$this->field->getValue().'" aria-describedby="error_'.$this->field->getId().'">
		<input type="hidden" id="'.$this->field->getId().'" name="'.$this->field->getId().'" value="'.$this->field->getValue().'" autocomplete="off">
		<datalist id = "'.$datalistId.'" ></datalist>';
	}
}