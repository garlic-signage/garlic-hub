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
 * Abstract class responsible for rendering input fields with attributes and properties.
 */
abstract class AbstractInputFieldRenderer
{
	/** @var array<string,mixed>|array<empty,empty> */
	protected array $attributes = [];
	protected FieldInterface $field;

	abstract public function render(FieldInterface $field): string;

	protected function buildAttributes(): string
	{
		$this->initAttributes()->addBaseAttributes()->addCustomAttributes()->addValidationAttributes();

		$parts = [];
		foreach ($this->attributes as $key => $value)
		{
			$parts[] = sprintf('%s="%s"', $key, $value);
		}
		return implode(' ', $parts);
	}

	protected function addBaseAttributes(): static
	{
		$this->attributes = [
			'name'  => $this->field->getName(),
			'id'    => $this->field->getId(),
			'value' => $this->field->getValue()
		];
		if (!empty($this->field->getTitle()))
			$this->attributes['title'] = $this->field->getTitle();

		if (!empty($this->field->getLabel()))
			$this->attributes['label'] = $this->field->getLabel();

		return $this;
	}

	protected function initAttributes(): static
	{
		$this->attributes = [];
		return $this;
	}

	protected function addCustomAttributes(): static
	{
		foreach ($this->field->getAttributes() as $name => $value)
		{
			$this->attributes[$name] = $value;
		}

		return $this;
	}

	protected function addValidationAttributes(): static
	{
		foreach ($this->field->getValidationRules() as $rule => $value)
		{
			if ($value === true)
				$this->attributes[$rule] = $rule;
			else
				$this->attributes[$rule] = $value;
		}

		return $this;
	}

}