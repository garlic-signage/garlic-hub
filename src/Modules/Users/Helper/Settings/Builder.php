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

use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Builder class is responsible for managing form element creation, parameter configuration,
 * permission validation, and handling user input.
 */
class Builder
{
	private readonly FormElementsCreator $formElementsCreator;
	private readonly AclValidator $aclValidator;
	private readonly Validator $validator;
	private readonly Parameters $parameters;
	private int $UID;
	public function __construct(AclValidator $aclValidator, Parameters $parameters, Validator $validator, FormElementsCreator $formElementsCreator)
	{
		$this->aclValidator         = $aclValidator;
		$this->parameters           = $parameters;
		$this->validator            = $validator;
		$this->formElementsCreator  = $formElementsCreator;
	}

	public function init(Session $session): static
	{
		/** @var array{UID: int} $user */
		$user = $session->get('user');
		$this->UID      = $user['UID'];

		return $this;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function configNewParameter(): void
	{
		if (!$this->aclValidator->isSimpleAdmin($this->UID))
			return;

		$this->parameters->addUserName();
		$this->parameters->addUserEmail();
		$this->parameters->addUserStatus();
		$this->parameters->addUserLocale();
	}

	/**
	 * @param array{"UID": int, "company_id": int, ...} $user
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function configEditParameter(array $user): void
	{
		if (!$this->aclValidator->isAdmin($this->UID, $user))
			return;

		$this->parameters->addUserName();
		$this->parameters->addUserEmail();
		$this->parameters->addUserStatus();
		$this->parameters->addUserLocale();
	}

	/**
	 * @param array<string,mixed> $user
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function buildForm(array $user): array
	{
		$form       = [];
		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_NAME))
		{
			$form['username'] = $this->formElementsCreator->createUserNameField(
				$user[Parameters::PARAMETER_USER_NAME] ?? ''
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_EMAIL))
		{
			$form['email'] = $this->formElementsCreator->createEmailField(
				$user[Parameters::PARAMETER_USER_EMAIL] ?? ''
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_STATUS))
			$form['status'] = $this->formElementsCreator->createUserStatusField((string) ($user[Parameters::PARAMETER_USER_STATUS] ?? 2));

		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_LOCALE))
			$form['locale'] = $this->formElementsCreator->createUserLocaleField($user[Parameters::PARAMETER_USER_LOCALE] ?? 'en_US');


		$tokens = $user['tokens'] ?? [];
		foreach ($tokens as $token)
		{
			$tokenObj = $this->formElementsCreator->createClipboardTextField(
				bin2hex($token['token']),
				$token['purpose'],
				$token['expires_at']
			);

			$form['token_' . $token['token']] = $tokenObj;
		}

		if (isset($user[Parameters::PARAMETER_USER_ID]))
			$form['UID'] = $this->formElementsCreator->createHiddenUIDField((int) $user[Parameters::PARAMETER_USER_ID]);

		$form['csrf_token'] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}

	/**
	 * @return list<array<string,string>>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function addButtons(): array
	{
		return [
				$this->formElementsCreator->addResetPasswordButton()
		];
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
	public function handleUserInput(array $post): array
	{
		$this->parameters->setUserInputs($post)
			->parseInputAllParameters();

		return $this->validator->validateUserInput();
	}

}