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

namespace App\Modules\Profile\Helper\Password;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * A readonly class responsible for building forms and handling user inputs.
 * This class utilizes form element creation, input validation, and parameter parsing functionality.
 */
readonly class Builder
{
	private FormElementsCreator $formElementsCreator;
	private Validator $validator;
	private Parameters $parameters;
	public function __construct(Parameters $parameters, Validator $validator, FormElementsCreator $formElementsCreator)
	{
		$this->parameters           = $parameters;
		$this->validator            = $validator;
		$this->formElementsCreator  = $formElementsCreator;
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function buildForm(string $pattern, string $passwordToken = ''): array
	{
		$form       = [];
		$form['password']         = $this->formElementsCreator->createPasswordField('', $pattern);
		$form['password_confirm'] = $this->formElementsCreator->createPasswordConfirmField('');
		$form['csrf_token']       = $this->formElementsCreator->createCSRFTokenField();
		if ($passwordToken !== '')
			$form['password_token'] = $this->formElementsCreator->createPasswordTokenField($passwordToken);

		return $this->formElementsCreator->prepareForm($form);
	}


	/**
	 * @param array<string,mixed> $post
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function handleUserInput(array $post, string $passwordPattern): array
	{
		$this->parameters->setUserInputs($post)->parseInputAllParameters();

		return $this->validator->validateUserInput($passwordPattern);
	}

}