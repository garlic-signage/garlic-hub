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
use App\Framework\Exceptions\FrameworkException;
use Exception;

/**
 * Class responsible for building and managing form fields. It leverages field factories
 * and render factories to create and render different types of form fields. Additionally,
 * it handles CSRF token management for enhanced security.
 */
class FormBuilder
{
	private FieldsFactory $fieldsFactory;
	private FieldsRenderFactory $fieldsRenderFactory;
	private CsrfToken $csrfToken;

	public function __construct(FieldsFactory $fieldsFactory, FieldsRenderFactory $fieldsRenderFactory, CsrfToken $csrfToken)
	{
		$this->fieldsFactory       = $fieldsFactory;
		$this->fieldsRenderFactory = $fieldsRenderFactory;
		$this->csrfToken            = $csrfToken;
	}

	/**
	 * @param array<string,FieldInterface> $formFields
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 */
	public function prepareForm(array $formFields): array
	{
		$hidden = [];
		$visible = [];

		foreach ($formFields as $element)
		{
			if ($element->getType() === FieldType::HIDDEN || $element->getType() === FieldType::CSRF)
			{
				$hidden[] = [
					'HIDDEN_HTML_ELEMENT'        => $this->renderField($element)
				];
				continue;
			}

			$visible[] = [
				'HTML_ELEMENT_ID'    => $element->getId(),
				'LANG_ELEMENT_LABEL' => $element->getLabel(),
				'LANG_ELEMENT_NAME'  => $element->getName(),
				'ELEMENT_MUST_FIELD' => '', //$element->getAttribute('required') ? '*' : '',
				'HTML_ELEMENT'       => $this->renderField($element)
			];
		}

		return ['hidden' => $hidden, 'visible' => $visible];
	}


	/**
	 * @param array<string,mixed> $attributes
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function createField(array $attributes = []): FieldInterface|ClipboardTextField|UrlField|CheckboxField
	{
		return match ($attributes['type']) {
			FieldType::TEXT           => $this->fieldsFactory->createTextField($attributes),
			FieldType::NUMBER         => $this->fieldsFactory->createNumberField($attributes),
			FieldType::DROPDOWN       => $this->fieldsFactory->createDropdownField($attributes),
			FieldType::URL            => $this->fieldsFactory->createUrlField($attributes),
			FieldType::CHECKBOX       => $this->fieldsFactory->createCheckboxField($attributes),
			FieldType::AUTOCOMPLETE   => $this->fieldsFactory->createAutocompleteField($attributes),
			FieldType::PASSWORD       => $this->fieldsFactory->createPasswordField($attributes),
			FieldType::EMAIL          => $this->fieldsFactory->createEmailField($attributes),
			FieldType::HIDDEN         => $this->fieldsFactory->createHiddenField($attributes),
			FieldType::CLIPBOARD_TEXT => $this->fieldsFactory->createClipboardTextField($attributes),
			FieldType::CSRF           => $this->fieldsFactory->createCsrfTokenField($attributes, $this->csrfToken),
			default => throw new FrameworkException('Invalid field type'),
		};
	}

	public function renderField(FieldInterface $field): string
	{
		return $this->fieldsRenderFactory->getRenderer($field);
	}
}