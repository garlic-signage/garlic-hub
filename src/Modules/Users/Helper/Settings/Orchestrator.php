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

namespace App\Modules\Users\Helper\Settings;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Users\Services\UsersAdminService;
use Doctrine\DBAL\Exception;

/**
 * Handles orchestration of validation, fetching, and saving of users by an admin.
 */
class Orchestrator
{
	/** @var array{UID: int,
	 * company_id: int,
	 * status: int,
	 * locale: string,
	 * email:string,
	 * username:string,
	 * tokens:list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 * }|array{}
	 */
	private array $user = [];

	public function __construct(
		private readonly Session                  $session,
		private readonly UsersAdminService        $usersAdminService,
		private readonly SettingsParametersPolicy $settingsParametersPolicy,
		private readonly FormInputHandler         $formInputHandler,
		private readonly SettingsFormBuilder      $settingsFormBuilder,
		private readonly Translator               $translator
	) {}

	/**
	 * Returns an empty array when the user does not exist.
	 *
	 * @return array{UID: int,
	 *  company_id: int,
	 *  status: int,
	 *  locale: string,
	 *  email:string,
	 *  username:string,
	 *  tokens:list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 * }|array{}
	 * @throws Exception
	 * @throws UserException
	 */
	public function loadUserForEdit(int $UID): array
	{
		$this->user = $this->usersAdminService->loadForAdminEdit($UID);

		return $this->user;
	}

	/**
	 * @param array<string,mixed> $userInput
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function buildCreateForm(array $userInput = []): array
	{
		$this->settingsParametersPolicy->addCreateFormElements();

		return $this->settingsFormBuilder->buildForm($userInput);
	}

	/**
	 * The user must be loaded with loadUserForEdit() before.
	 * Without user input, the form is filled with the loaded user.
	 *
	 * @param array<string,mixed> $userInput
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function buildEditForm(array $userInput = []): array
	{
		$this->settingsParametersPolicy->addEditFormElements($this->getLoadedUser());

		$form = $this->settingsFormBuilder->buildForm($userInput !== [] ? $userInput : $this->user);
		$form['additional_buttons'] = $this->settingsFormBuilder->buildButtons();

		return $form;
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function storeCreateSettings(array $post): array
	{
		$this->settingsParametersPolicy->addCreateFormElements();
		$errors = $this->formInputHandler->validate($post);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		/** @var array{username:string, email:string, locale?: string, status?: int} $saveData */
		$saveData = $this->formInputHandler->getParsed();
		try
		{
			$this->usersAdminService->insertNewUser($saveData);
		}
		catch (TranslatableModuleException $e)
		{
			return $this->formError($e);
		}

		// Todo: Eliminate this workaround with a better process
		$this->session->set(TokenPurposes::INITIAL_PASSWORD->value.'_token', bin2hex($this->usersAdminService->getToken()));

		return ['success' => true];
	}

	/**
	 * The user must be loaded with loadUserForEdit() before, so the ACL is checked.
	 *
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function storeEditSettings(int $UID, array $post): array
	{
		$errors = $this->validateEdit($post);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		/** @var array{username:string, email:string, locale?: string, status?: int} $saveData */
		$saveData = $this->formInputHandler->getParsed();
		// a save without changes affects no rows but is not a failure
		try
		{
			$this->usersAdminService->updateUser($UID, $saveData);
		}
		catch (TranslatableModuleException $e)
		{
			return $this->formError($e);
		}

		return ['success' => true];
	}

	/**
	 * The user must be loaded with loadUserForEdit() before, so the ACL is checked.
	 *
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function resetPassword(int $UID, array $post): array
	{
		$errors = $this->validateEdit($post);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		try
		{
			$token = $this->usersAdminService->createPasswordResetToken($UID);
		}
		catch (TranslatableModuleException $e)
		{
			return $this->formError($e);
		}

		// Todo: Eliminate this workaround with a better process
		$this->session->set(TokenPurposes::PASSWORD_RESET->value.'_token', bin2hex($token));

		return ['success' => true];
	}

	/**
	 * @param array<string,mixed> $post
	 * @return string[]
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function validateEdit(array $post): array
	{
		$this->settingsParametersPolicy->addEditFormElements($this->getLoadedUser());

		return $this->formInputHandler->validate($post);
	}

	/**
	 * @return array{UID: int, company_id: int, ...}
	 * @throws ModuleException
	 */
	private function getLoadedUser(): array
	{
		if ($this->user === [])
			throw new ModuleException('users', 'No user loaded for editing.');

		return $this->user;
	}

	/**
	 * Expected errors are shown in the form again instead of the standard error page.
	 *
	 * @return array{success: false, errors: string[]}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function formError(TranslatableModuleException $exception): array
	{
		return ['success' => false, 'errors' => [$this->translator->translateException($exception)]];
	}
}
