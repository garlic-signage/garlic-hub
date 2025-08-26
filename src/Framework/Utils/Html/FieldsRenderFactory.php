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

use InvalidArgumentException;

/**
 * Factory class for rendering various types of fields.
 * The class is responsible for returning appropriate renderers for given field types.
 */
class FieldsRenderFactory
{
	/** @var array<string,FieldRenderInterface>  */
	private array $rendererCache = [];

	public function getRenderer(FieldInterface $field): string
	{
		return match (true)
		{
			$field instanceof TextField          => $this->getCachedRenderer(TextRenderer::class)->render($field),
			$field instanceof NumberField        => $this->getCachedRenderer(NumberRenderer::class)->render($field),
			$field instanceof DropdownField      => $this->getCachedRenderer(DropdownRenderer::class)->render($field),
			$field instanceof AutocompleteField  => $this->getCachedRenderer(AutocompleteRenderer::class)->render($field),
			$field instanceof EmailField         => $this->getCachedRenderer(EmailRenderer::class)->render($field),
			$field instanceof PasswordField      => $this->getCachedRenderer(PasswordRenderer::class)->render($field),
			$field instanceof HiddenField        => $this->getCachedRenderer(HiddenRenderer::class)->render($field),
			$field instanceof ClipboardTextField => $this->getCachedRenderer(ClipboardTextRenderer::class)->render($field),
			$field instanceof UrlField           => $this->getCachedRenderer(UrlRenderer::class)->render($field),
			$field instanceof CheckboxField      => $this->getCachedRenderer(CheckboxRenderer::class)->render($field),
			$field instanceof CsrfTokenField     => $this->getCachedRenderer(CsrfTokenRenderer::class)->render($field),
			default => throw new InvalidArgumentException('Unsupported field type: ' . get_class($field)),
		};
	}

	protected function getCachedRenderer(string $rendererClass): FieldRenderInterface
	{
		if (!is_a($rendererClass, FieldRenderInterface::class, true))
			throw new InvalidArgumentException(sprintf(
				'Renderer class "%s" must implement "%s".',
				$rendererClass,
				FieldRenderInterface::class
			));

		if (!isset($this->rendererCache[$rendererClass]))
			$this->rendererCache[$rendererClass] = new $rendererClass();

		return $this->rendererCache[$rendererClass];
	}
}