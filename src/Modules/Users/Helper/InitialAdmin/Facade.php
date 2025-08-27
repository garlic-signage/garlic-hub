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

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Services\UsersAdminCreateService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class Facade
 *
 * This class serves as a unified API for managing user-related functionalities in an application.
 * It operates as a "facade," providing simplified methods to interact with underlying services,
 * configurations, and settings required for user management, including processes such as
 * user creation, form preparation, and error handling.
 *
 * Responsibilities include:
 * - Initializing system with a translator instance.
 * - Verifying if specific operations are permitted under certain circumstances.
 * - Handling user creation and configuration of form parameters.
 * - Fetching and translating service error messages.
 * - Preparing data for user interface templates.
 */
class Facade
{
	private readonly Builder $settingsFormBuilder;
	private readonly UsersAdminCreateService $usersAdminService;
	private readonly Config $config;
	private readonly Parameters $settingsParameters;
	private Translator $translator;

	 public function __construct(Builder $settingsFormBuilder, UsersAdminCreateService $usersAdminService, Config $config, Parameters $settingsParameters)
	{
		$this->settingsFormBuilder = $settingsFormBuilder;
		$this->usersAdminService   = $usersAdminService;
		$this->config              = $config;
		$this->settingsParameters  = $settingsParameters;
	}

	/**
	 * @param Translator $translator
	 */
	public function init(Translator $translator): void
	{
		$this->translator = $translator;
	}

	/**
	 * @throws Exception
	 */
	public function isFunctionAllowed(): bool
	{
		if (file_exists(INSTALL_LOCK_FILE))
			return false;

		// means something happens with an installed lock file
		// create a lock file silently and send an error message
		if ($this->usersAdminService->hasAdminUser())
		{
			$this->usersAdminService->creatLockfile();
			$this->usersAdminService->logAlarm();
			return false;
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $post
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function configureUserFormParameter(array $post): array
	{
		$passwordPattern = $this->config->getConfigValue('password_pattern', 'main');

		return $this->settingsFormBuilder->handleUserInput($post, $passwordPattern);
	}

	/**
	 * @throws Exception
	 */
	public function storeUser(): int
	{
		/** @var array{username:string, email:string, locale: string, password: string} $saveData */
		$saveData  = array_combine(
			$this->settingsParameters->getInputParametersKeys(),
			$this->settingsParameters->getInputValuesArray()
		);

		return $this->usersAdminService->insertNewAdminUser($saveData, $this->config);
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
	 * @param array{username?:string, email?:string, locale?: string, password?:string, password_confirm?: string}  $post
	 * @return array<string,mixed>
	 *
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function prepareUITemplate(array $post): array
	{
		$passwordPattern = $this->config->getConfigValue('password_pattern', 'main');
		$title = $this->translator->translate('create_admin', 'users');
		$dataSections                      = $this->settingsFormBuilder->buildForm($post, $passwordPattern);
		$dataSections['title']             = $title;
		$dataSections['additional_css']    = ['/css/users/edit.css', '/css/profile/password.css'];
		$dataSections['footer_modules']    = ['/js/profile/password/init.js'];
		$dataSections['template_name']     = 'users/edit';
		$dataSections['form_action']       = '/create-initial';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $dataSections;
	}

}