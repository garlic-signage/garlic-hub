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

namespace App\Modules\Users\Helper\Settings;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Forms\AbstractBaseFormElementsCreator;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * This class is responsible for creating and managing form elements.
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
			'id' => 'username',
			'name' => 'username',
			'title' => $this->translator->translate('username', 'main'),
			'label' => $this->translator->translate('username', 'main'),
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
	public function createUserStatusField(string $value): FieldInterface
	{
		return  $this->formBuilder->createField([
			'type' => FieldType::DROPDOWN,
			'id' => 'status',
			'name' => 'status',
			'title' => $this->translator->translate(Parameters::PARAMETER_USER_STATUS, 'users'),
			'label' => $this->translator->translate(Parameters::PARAMETER_USER_STATUS, 'users'),
			'value' => $value,
			'options' => $this->translator->translateArrayForOptions(Parameters::PARAMETER_USER_STATUS.'_selects', 'users'),
			'options_zero' => false
		]);
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
			'id' => 'locale',
			'name' => 'locale',
			'title' => $this->translator->translate(Parameters::PARAMETER_USER_LOCALE, 'users'),
			'label' => $this->translator->translate(Parameters::PARAMETER_USER_LOCALE, 'users'),
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
	public function createClipboardTextField(string $value, string $purpose, string $expiresAt): ClipboardTextField
	{
		$http = isset($_SERVER['HTTPS']) ? 'https://': 'http://';
		$host = $_SERVER['HTTP_HOST'] ?? '';

		$purposeStr = $this->translator->translateArrayForOptions('purposes_selects', 'profile')[$purpose];
		$label = sprintf($this->translator->translate('verification_link', 'profile'),
			$purposeStr,
			$expiresAt
		);

		/** @var ClipboardTextField $object */
		$object = $this->formBuilder->createField([
			'type' => FieldType::CLIPBOARD_TEXT,
			'id' => $value,
			'label' => $label,
			'title' => $this->translator->translate('copy_to_clipboard', 'main'),
			'value' => $http.$host.'/force-password?token='.$value
		]);

		$object->setDeleteTitle($this->translator->translate('remove', 'main'));
		$object->setRefreshTitle($this->translator->translate('refresh', 'main'));

		return $object;
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
			'id' => 'email',
			'name' => 'email',
			'title' => $this->translator->translate('email', 'users'),
			'label' => $this->translator->translate('email', 'users'),
			'value' => $value,
			'rules' => ['required' => true],
			'default_value' => ''
		]);
	}


	/**
	 * @throws FrameworkException
	 */
	public function createHiddenUIDField(int $value): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::HIDDEN,
			'id' => 'UID',
			'name' => 'UID',
			'value' => $value,
		]);
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function addResetPasswordButton(): array
	{
		return [
			'ADDITIONAL_BUTTON_TYPE' => 'submit',
			'ADDITIONAL_BUTTON_NAME' => 'resetPassword',
			'LANG_ADDITIONAL_BUTTON' => $this->translator->translate('password_reset', 'profile')
		];
	}

}