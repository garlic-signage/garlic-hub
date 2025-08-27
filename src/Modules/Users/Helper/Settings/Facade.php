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
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Services\UsersAdminService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * The Facade class serves as a mediator between various system components, providing streamlined access
 * and functionality for managing user data and configurations. It acts as a single interface to complex
 * subsystems, including user management and settings services.
 */
class Facade
{
	private readonly Builder $settingsFormBuilder;
	private readonly UsersAdminService $usersAdminService;
	private readonly Parameters $settingsParameters;
	/** @var array{UID: int,
	 * company_id: int,
	 * status: int,
	 * locale: string,
	 * email:string,
	 * username:string,
	 * tokens:list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 * }
	 */
	private array $oldUser;
	private Translator $translator;

	 public function __construct(Builder $settingsFormBuilder, UsersAdminService $usersAdminService, Parameters $settingsParameters)
	{
		$this->settingsFormBuilder = $settingsFormBuilder;
		$this->usersAdminService   = $usersAdminService;
		$this->settingsParameters  = $settingsParameters;
	}

	public function init(Translator $translator, Session $session): void
	{
		$this->translator = $translator;
		$this->settingsFormBuilder->init($session);
		/** @var array{UID: int, username: string} $user */
		$user = $session->get('user');
		$this->usersAdminService->setUID($user['UID']);
	}

	/**
	 * @param int $UID
	 * @return array{UID: int,
	 *  company_id: int,
	 *  status: int,
	 *  locale: string,
	 *  email:string,
	 *  username:string,
	 *  tokens:list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 * }|array{}
	 * @throws Exception
	 */
	public function loadUserForEdit(int $UID): array
	{
		$user = $this->usersAdminService->loadForAdminEdit($UID);
		if ($user === [])
			return [];

		$this->oldUser = $user;
		return $user;
	}

	/**
	 * @param array<string,mixed> $post
	 * @return string[]
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function configureUserFormParameter(array $post): array
	{
		$UID = (int) ($post['UID'] ?? 0);
		if ($UID > 0)
		{
			$user = $this->usersAdminService->loadForAdminEdit($UID);
			if ($user === [])
				return [$this->translator->translate('user_not_found', 'users')];


			$this->oldUser =  $user;
			$this->settingsFormBuilder->configEditParameter($this->oldUser);
		}
		else
		{
			$this->settingsFormBuilder->configNewParameter();
		}

		return $this->settingsFormBuilder->handleUserInput($post);
	}


	/**
	 * @throws Exception
	 */
	public function storeUser(int $UID): int
	{
		/** @var array{username:string, email:string, locale?: string, status?: int} $saveData */
		$saveData  = array_combine(
			$this->settingsParameters->getInputParametersKeys(),
			$this->settingsParameters->getInputValuesArray()
		);

		if ($UID > 0)
			$id = $this->usersAdminService->updateUser($UID, $saveData);
		else
			$id = $this->usersAdminService->insertNewUser($saveData);

		return $id;
	}

	/**
	 * @throws Exception
	 */
	public function createPasswordResetToken(int $UID): string
	{
		return $this->usersAdminService->createPasswordResetToken($UID);
	}

	/**
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function getUserServiceErrors(): array
	{
		$errors     = $this->usersAdminService->getErrorMessages();
		$translated =[];
		foreach ($errors as $error)
		{
			$translated[] = $this->translator->translate($error, 'users');
		}
		return $translated;
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function buildCreateNewParameter(): void
	{
		$this->settingsFormBuilder->configNewParameter();
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function buildEditParameter(): void
	{
		$this->settingsFormBuilder->configEditParameter($this->oldUser);
	}

	/**
	 * @param array<string,string> $post
	 * @return array<string,mixed>
	 *
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function prepareUITemplate(array $post): array
	{
		$name = $this->oldUser['username'] ?? $this->translator->translate('add', 'users');

		$title = $this->translator->translate('core_data', 'users'). ': ' .$name;
		$dataSections                      = $this->settingsFormBuilder->buildForm($post);
		$dataSections['title']             = $title;
		$dataSections['additional_css']    = ['/css/users/edit.css'];
		$dataSections['footer_modules']    = ['/js/users/edit/init.js'];
		$dataSections['template_name']     = 'users/edit';
		$dataSections['form_action']       = '/users/edit';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');
		if (!empty($this->oldUser))
			$dataSections['additional_buttons'] = $this->settingsFormBuilder->addButtons();

		return $dataSections;
	}

}