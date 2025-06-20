<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace App\Modules\Profile\Helper\Password;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\PasswordField;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

readonly class FormElementsCreator
{
	private FormBuilder $formBuilder;

	private Translator $translator;


	public function __construct(FormBuilder $formBuilder, Translator $translator)
	{
		$this->formBuilder = $formBuilder;
		$this->translator = $translator;
	}

	/**
	 * @param array<string, mixed> $form
	 * @return array<string, mixed>
	 */
	public function prepareForm(array $form): array
	{
		return $this->formBuilder->prepareForm($form);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createPasswordField(string $value, string $pattern): FieldInterface
	{
		/** @var PasswordField $field */
		$field = $this->formBuilder->createField([
			'type' => FieldType::PASSWORD,
			'id' => 'password',
			'name' => 'password',
			'title' => $this->translator->translate('password_explanation', 'profile'),
			'label' => $this->translator->translate('password', 'profile'),
			'value' => $value,
			'rules' => ['required' => true, 'minlength' => 8],
			'default_value' => ''
		]);
		//$field->setPattern($pattern);

		return $field;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createPasswordConfirmField(string $value): FieldInterface
	{
		/** @var PasswordField $field */
		$field =  $this->formBuilder->createField([
			'type' => FieldType::PASSWORD,
			'id' => 'password_confirm',
			'name' => 'password_confirm',
			'title' => $this->translator->translate('password_explanation', 'profile'),
			'label' => $this->translator->translate('password_confirm', 'profile'),
			'value' => $value,
			'rules' => ['required' => true, 'minlength' => 8],
			'default_value' => ''
		]);

		return $field;
	}

	/**
	 * @throws FrameworkException
	 */
	public function createCSRFTokenField(): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::CSRF,
			'id'   => BaseEditParameters::PARAMETER_CSRF_TOKEN,
			'name' => BaseEditParameters::PARAMETER_CSRF_TOKEN,
		]);
	}

}