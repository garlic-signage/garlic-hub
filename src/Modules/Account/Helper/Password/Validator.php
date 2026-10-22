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

namespace App\Modules\Account\Helper\Password;

use App\Framework\Validation\BaseValidator;
use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;

/**
 * Validator class extends the functionality of BaseValidator to handle
 * input validation operations, such as validating user inputs and form tokens.
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
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateUserInput(string $passwordPattern): array
	{
		$errors = $this->validateFormCsrfToken($this->inputEditParameters);

		$password = $this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_PASSWORD);
		if ($password === '')
			$errors[] = $this->translator->translate('no_password', 'account');

		$passwordConfirm = $this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_PASSWORD_CONFIRM);
		if ($passwordConfirm === '')
			$errors[] = $this->translator->translate('no_password_confirm', 'account');

		if ($password !== $passwordConfirm)
			$errors[] = $this->translator->translate('no_passwords_match', 'account');

		if (!$this->validatePassword($password, $passwordPattern))
			$errors[] = $this->translator->translate('password_explanation', 'account');

		return $errors;
	}
}