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

namespace App\Modules\Users\Helper\InitialAdmin;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\AbstractBaseFormElementsCreator;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\PasswordField;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class FormElementsCreator
 *
 * Responsible for creating various form input fields with specific configurations.
 */
class FormElementsCreator extends AbstractBaseFormElementsCreator
{

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createUserNameField(string $value): FieldInterface
	{
		return  $this->formBuilder->createField([
			'type' => FieldType::TEXT,
			'id' => Parameters::PARAMETER_ADMIN_NAME,
			'name' => Parameters::PARAMETER_ADMIN_NAME,
			'title' => $this->translator->translate(Parameters::PARAMETER_ADMIN_NAME, 'main'),
			'label' => $this->translator->translate(Parameters::PARAMETER_ADMIN_NAME, 'main'),
			'value' => $value,
			'rules' => ['required' => true, 'minlength' => 2],
			'default_value' => ''
		]);
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
			'id' => Parameters::PARAMETER_ADMIN_PASSWORD,
			'name' => Parameters::PARAMETER_ADMIN_PASSWORD,
			'title' => $this->translator->translate('password_explanation', 'profile'),
			'label' => $this->translator->translate(Parameters::PARAMETER_ADMIN_PASSWORD, 'profile'),
			'value' => $value,
			'rules' => ['required' => true, 'minlength' => 8],
			'default_value' => ''
		]);
		$field->setPattern($pattern);

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
			'id' => Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM,
			'name' => Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM,
			'title' => $this->translator->translate('password_explanation', 'profile'),
			'label' => $this->translator->translate(Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'profile'),
			'value' => $value,
			'rules' => ['required' => true, 'minlength' => 8],
			'default_value' => ''
		]);

		return $field;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createUserLocaleField(string $value): FieldInterface
	{
		return  $this->formBuilder->createField([
			'type' => FieldType::DROPDOWN,
			'id' => Parameters::PARAMETER_ADMIN_LOCALE,
			'name' => Parameters::PARAMETER_ADMIN_LOCALE,
			'title' => $this->translator->translate(Parameters::PARAMETER_ADMIN_LOCALE, 'users'),
			'label' => $this->translator->translate(Parameters::PARAMETER_ADMIN_LOCALE, 'users'),
			'value' => $value,
			'options' => $this->translator->translateArrayForOptions('languages', 'menu'),
			'options_zero' => false
		]);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createEmailField(string $value): FieldInterface
	{
		return  $this->formBuilder->createField([
			'type' => FieldType::EMAIL,
			'id' => Parameters::PARAMETER_ADMIN_EMAIL,
			'name' => Parameters::PARAMETER_ADMIN_EMAIL,
			'title' => $this->translator->translate(Parameters::PARAMETER_ADMIN_EMAIL, 'users'),
			'label' => $this->translator->translate(Parameters::PARAMETER_ADMIN_EMAIL, 'users'),
			'value' => $value,
			'rules' => ['required' => true],
			'default_value' => ''
		]);
	}
}