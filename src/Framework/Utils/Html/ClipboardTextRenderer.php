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
 * Renders a clipboard-enabled text input field with additional action buttons
 * for copying, deleting, and refreshing the value of the field.
 *
 * This class is designed to generate HTML for fields that implement
 * ClipboardTextField. It ensures that the field includes interactive
 * elements for user interaction, such as clipboard copying and refresh actions.
 *
 * Implements the FieldRenderInterface to ensure consistency with
 * other field renderers.
 */
class ClipboardTextRenderer extends AbstractInputFieldRenderer implements FieldRenderInterface
{
	public function render(FieldInterface $field): string
	{
		$this->field = $field;
		$id = $this->field->getId();
		if (!($this->field instanceof ClipboardTextField))
			return '';

		return '<input type="text" id="'.$id.'" class="verification-link" title="'.$this->field->getTitle().'" value="'.$this->field->getValue().'" readonly>
<button type="button" data-id="'.$id.'" class="copy-verification-link" title="'.$this->field->getTitle().'."><i class="bi bi-clipboard"></i></button>
<button type="button" data-id="'.$id.'" class="delete-verification-link" title="'.$this->field->getDeleteTitle().'."><i class="bi bi-trash"></i></button>
<button type="button" data-id="'.$id.'" class="refresh-verification-link" title="'.$this->field->getRefreshTitle().'."><i class="bi bi-arrow-clockwise"></i></button>
';
	}
}