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

namespace App\Modules\Users\Helper\InitialAdmin;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Users\Services\UsersAdminCreateService;
use Doctrine\DBAL\Exception;

/**
 * Creates the very first admin user. Works without a logged-in user,
 * so no UserSession must be touched here.
 */
readonly class Orchestrator
{
	public function __construct(
		private UsersAdminCreateService $usersAdminCreateService,
		private FormInputHandler        $formInputHandler,
		private SettingsFormBuilder     $settingsFormBuilder,
		private Config                  $config,
		private Translator              $translator
	) {}

	/**
	 * @throws Exception
	 */
	public function isFunctionAllowed(): bool
	{
		if (file_exists(INSTALL_LOCK_FILE))
			return false;

		// means something happens with an installed lock file
		// create a lock file silently and send an error message
		if ($this->usersAdminCreateService->hasAdminUser())
		{
			$this->usersAdminCreateService->creatLockfile();
			$this->usersAdminCreateService->logAlarm();
			return false;
		}

		return true;
	}

	/**
	 * @param array{username?:string, email?:string, locale?: string} $post
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(array $post): array
	{
		return $this->settingsFormBuilder->buildForm($post, $this->getPasswordPattern());
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function store(array $post): array
	{
		$errors = $this->formInputHandler->validate($post, $this->getPasswordPattern());
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		/** @var array{username:string, email:string, locale: string, password: string} $saveData */
		$saveData = $this->formInputHandler->getParsed();
		try
		{
			$this->usersAdminCreateService->insertNewAdminUser($saveData, $this->config);
		}
		catch (TranslatableModuleException $e)
		{
			// the form is shown again with the error instead of the standard error page
			return ['success' => false, 'errors' => [$this->translator->translateException($e)]];
		}

		return ['success' => true];
	}

	private function getPasswordPattern(): string
	{
		return $this->config->getConfigValue('password_pattern', 'main');
	}
}
