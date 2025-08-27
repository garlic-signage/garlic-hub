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

use App\Framework\Core\BaseValidator;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class Validator
 *
 * A class responsible for validating user input, including form tokens, usernames,
 * email addresses, and password integrity.
 */
class Validator extends BaseValidator
{
	private Parameters $inputEditParameters;

	public function __construct(Translator $translator, Parameters $inputEditParameters, CsrfToken $csrfToken)
	{
		parent::__construct($translator, $csrfToken);
		$this->inputEditParameters = $inputEditParameters;
	}

	/**
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function validateUserInput(string $passwordPattern): array
	{
		$errors = $this->validateFormCsrfToken($this->inputEditParameters);

		if ($this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_ADMIN_NAME) === '')
			$errors[] = $this->translator->translate('no_username', 'users');

		if (!$this->isEmail($this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_ADMIN_EMAIL)))
			$errors[] = $this->translator->translate('no_email', 'users');

		$password = $this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_ADMIN_PASSWORD);
		if ($password === '')
			$errors[] = $this->translator->translate('no_password', 'profile');

		$passwordConfirm = $this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM);
		if ($passwordConfirm === '')
			$errors[] = $this->translator->translate('no_password_confirm', 'profile');

		if ($password !== $passwordConfirm)
			$errors[] = $this->translator->translate('no_passwords_match', 'profile');

		if (!$this->validatePassword($password, $passwordPattern))
			$errors[] = $this->translator->translate('password_explanation', 'profile');

		return $errors;
	}
}