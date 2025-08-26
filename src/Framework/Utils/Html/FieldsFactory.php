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

use App\Framework\Core\CsrfToken;
use Exception;

/**
 * Factory class responsible for creating various field objects.
 */
class FieldsFactory
{
	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createTextField(array $attributes): TextField
	{
		return new TextField($attributes);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createNumberField(array $attributes): NumberField
	{
		return new NumberField($attributes);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createAutocompleteField(array $attributes): AutocompleteField
	{
		return new AutocompleteField($attributes);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createDropdownField(array $attributes): DropdownField
	{
		return new DropdownField($attributes);
	}


	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createEmailField(array $attributes): EmailField
	{
		return new EmailField($attributes);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createPasswordField(array $attributes): PasswordField
	{
		return new PasswordField($attributes);
	}

	/**
	 * @param array<string,mixed> $attributes
	 * @throws Exception
	 */
	public function createCsrfTokenField(array $attributes, CsrfToken $csrfToken): CsrfTokenField
	{
		return new CsrfTokenField($attributes, $csrfToken);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createClipboardTextField(array $attributes): ClipboardTextField
	{
		return new ClipboardTextField($attributes);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createUrlField(array $attributes): UrlField
	{
		return new UrlField($attributes);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createCheckboxField(array $attributes): CheckboxField
	{
		return new CheckboxField($attributes);
	}


	/**
	 * @param array<string,mixed> $attributes
	 */
	public function createHiddenField(array $attributes): HiddenField
	{
		return new HiddenField($attributes);
	}

}