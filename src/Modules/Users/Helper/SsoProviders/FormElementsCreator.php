<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Users\Helper\SsoProviders;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\AbstractBaseFormElementsCreator;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\FieldType;

/**
 * The form of an SSO provider. The client secret is never shown: the field stays empty.
 */
class FormElementsCreator extends AbstractBaseFormElementsCreator
{
	private const string MODULE_NAME = 'users';

	/**
	 * @param array{provider_id:int, name:string, issuer:string, client_id:string, allowed_domains:string, enabled:bool, has_secret:bool} $values
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(array $values): array
	{
		$form = [
			'name'            => $this->text('name', 'sso_name', $values['name'], ['required' => true, 'maxlength' => 100]),
			'issuer'          => $this->text('issuer', 'sso_issuer', $values['issuer'], ['required' => true], 'https://accounts.google.com'),
			'client_id'       => $this->text('client_id', 'sso_client_id', $values['client_id'], ['required' => true]),
			'client_secret'   => $this->formBuilder->createField([
				'type'       => FieldType::PASSWORD,
				'id'         => 'client_secret',
				'name'       => 'client_secret',
				'label'      => $this->translator->translate('sso_client_secret', self::MODULE_NAME),
				'value'      => '',
				'rules'      => $values['has_secret'] ? [] : ['required' => true],
				'attributes' => ['autocomplete' => 'new-password', 'placeholder' => $values['has_secret'] ? $this->translator->translate('sso_secret_stored', self::MODULE_NAME) : '']
			]),
			'allowed_domains' => $this->text('allowed_domains', 'sso_allowed_domains', $values['allowed_domains'], [], 'example.com, example.org'),
			'enabled'         => $this->checkbox($values['enabled']),
			'provider_id'     => $this->formBuilder->createField(['type' => FieldType::HIDDEN, 'id' => 'provider_id', 'name' => 'provider_id', 'value' => $values['provider_id']]),
			'csrf_token'      => $this->createCSRFTokenField()
		];

		return $this->prepareForm($form);
	}

	/**
	 * @return list<array<string,string>>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildButtons(bool $isStored): array
	{
		if (!$isStored)
			return [];

		return [[
			'ADDITIONAL_BUTTON_TYPE' => 'submit',
			'ADDITIONAL_BUTTON_NAME' => 'delete',
			'LANG_ADDITIONAL_BUTTON' => $this->translator->translate('delete', 'main'),
			// the users of the provider lose their link to it
			'ADDITIONAL_BUTTON_CONFIRM' => $this->translator->translate('sso_delete_confirm', self::MODULE_NAME)
		]];
	}

	/**
	 * @param array<string,mixed> $rules
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function text(string $id, string $label, string $value, array $rules, string $placeholder = ''): \App\Framework\Utils\Html\FieldInterface
	{
		return $this->formBuilder->createField([
			'type'       => FieldType::TEXT,
			'id'         => $id,
			'name'       => $id,
			'label'      => $this->translator->translate($label, self::MODULE_NAME),
			'value'      => $value,
			'rules'      => $rules,
			'attributes' => $placeholder !== '' ? ['placeholder' => $placeholder] : []
		]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function checkbox(bool $checked): CheckboxField
	{
		$field = $this->formBuilder->createField([
			'type'  => FieldType::CHECKBOX,
			'id'    => 'enabled',
			'name'  => 'enabled',
			'label' => $this->translator->translate('sso_enabled', self::MODULE_NAME),
			// @infection-ignore-all the checkbox renderer shows the label, not the title
			'title' => $this->translator->translate('sso_enabled', self::MODULE_NAME)
		]);
		if (!$field instanceof CheckboxField)
			throw new FrameworkException('No checkbox.');

		$field->setChecked($checked);

		return $field;
	}
}
