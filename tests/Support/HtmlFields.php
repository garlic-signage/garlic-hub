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

namespace Tests\Support;

use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\FieldInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reads the html of form fields as a browser would: the attributes of the input.
 */
final class HtmlFields
{
	/**
	 * @param array<string,FieldInterface> $form fields by name
	 * @return array<string,array<string,string>> the attributes of the input (or select) of every field by the name of the field
	 */
	public static function attributesOf(FormBuilder $formBuilder, array $form): array
	{
		return self::fromPrepared($formBuilder->prepareForm($form));
	}

	/**
	 * @param array<string,mixed> $prepared a form as FormBuilder::prepareForm() gives it (hidden and visible fields)
	 * @return array<string,array<string,string>> the attributes of the input (or select) of every field by the name of the field
	 */
	public static function fromPrepared(array $prepared): array
	{
		$result = [];
		/** @var list<array<string,string>> $visible */
		$visible = $prepared['visible'] ?? [];
		/** @var list<array<string,string>> $hidden */
		$hidden  = $prepared['hidden'] ?? [];
		foreach (array_merge(array_column($visible, 'HTML_ELEMENT'), array_column($hidden, 'HIDDEN_HTML_ELEMENT')) as $html)
		{
			$element = self::element($html);
			if ($element === null)
				continue;

			$attributes = [];
			foreach ($element->attributes ?? [] as $attribute)
				$attributes[$attribute->name] = $attribute->value;

			$result[$attributes['name'] ?? $attributes['id'] ?? $element->tagName] = $attributes;
		}

		return $result;
	}

	private static function element(string $html): ?DOMElement
	{
		$document = new DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
		libxml_clear_errors();
		$nodes = (new DOMXPath($document))->query('//input | //select | //textarea');

		$node = $nodes === false ? null : $nodes->item(0);

		return $node instanceof DOMElement ? $node : null;
	}
}
