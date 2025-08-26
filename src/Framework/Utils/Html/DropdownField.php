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
 * DropdownField is a subclass of AbstractInputField used to represent
 * a dropdown field with configurable options and behavior.
 *
 * It enables setup of dropdown options and control over the default behavior
 * regarding whether an "empty" or "zero" option is included.
 *
 * Properties include:
 * - `options`: An associative array defining the available dropdown options.
 * - `optionsZero`: A boolean flag to determine if an initial zero option is provided.
 *
 * Provides methods for retrieving the configured options and the state of the zero option.
 */
class DropdownField extends AbstractInputField
{
	/** @var array<string,mixed> */
	private array $options;
	private bool $optionsZero;

	public function __construct(array $attributes)
	{
		parent::__construct($attributes);
		$this->options = $attributes['options'];
		$this->optionsZero = $attributes['options_zero'] ?? true;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	public function isOptionsZero(): bool
	{
		return $this->optionsZero;
	}




}