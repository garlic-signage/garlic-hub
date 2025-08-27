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
 * Extends the functionality of BaseValidator to include specific validation
 * logic for user inputs, such as validating CSRF tokens, usernames, and emails.
 *
 * This class relies on Translator for error message translation and handles
 * input parameters via the Parameters class. It is designed to perform precise
 * validation on user-provided data during form submissions or similar processes.
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
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function validateUserInput(): array
	{
		$errors = $this->validateFormCsrfToken($this->inputEditParameters);

		if ($this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_USER_NAME) === '')
			$errors[] = $this->translator->translate('no_username', 'users');

		$email = $this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_USER_EMAIL);
		if ($email === '' || !$this->isEmail($email))
			$errors[] = $this->translator->translate('no_email', 'users');

		return $errors;
	}


}