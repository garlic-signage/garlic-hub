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

namespace App\Modules\Profile\Helper\Password;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Profile\Services\UserTokenService;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;

/**
 * Handles the password change of the logged-in user and the forced password change via token.
 * The forced change works without a logged-in user, so UserSession is only touched by storePassword().
 */
readonly class Orchestrator
{
	public function __construct(
		private UserSession         $userSession,
		private ProfileService      $profileService,
		private UserTokenService    $userTokenService,
		private FormInputHandler    $formInputHandler,
		private SettingsFormBuilder $settingsFormBuilder,
		private Config              $config,
		private Translator          $translator
	) {}

	/**
	 * Returns an empty array when the token is invalid or not meant for a password change.
	 *
	 * @return array{UID:int, company_id:int, username:string, status:int, purpose:string}|array{}
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	public function loadTokenUser(string $passwordToken): array
	{
		if (!ctype_xdigit($passwordToken) || strlen($passwordToken) % 2 !== 0)
			return [];

		$binToken = hex2bin($passwordToken);
		if ($binToken === false)
			return [];

		$user = $this->userTokenService->findByToken($binToken);
		if ($user === null)
			return [];

		if ($user['purpose'] !== TokenPurposes::PASSWORD_RESET->value &&
			$user['purpose'] !== TokenPurposes::INITIAL_PASSWORD->value)
			return [];

		return $user;
	}

	/**
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(string $passwordToken = ''): array
	{
		return $this->settingsFormBuilder->buildForm($this->getPasswordPattern(), $passwordToken);
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
	public function storePassword(array $post): array
	{
		$errors = $this->formInputHandler->validate($post, $this->getPasswordPattern());
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		$UID = $this->userSession->getUID();
		$this->profileService->setUID($UID);
		if ($this->profileService->updatePassword($UID, $this->getParsedPassword()) === 0)
			return ['success' => false, 'errors' => $this->translateServiceErrors()];

		return ['success' => true];
	}

	/**
	 * The token must be checked with loadTokenUser() before.
	 *
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function storeForcedPassword(int $UID, string $passwordToken, array $post): array
	{
		$errors = $this->formInputHandler->validate($post, $this->getPasswordPattern());
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		$binToken = hex2bin($passwordToken);
		if ($binToken === false)
			return ['success' => false, 'errors' => [$this->translator->translate('token_error', 'profile')]];

		$this->profileService->setUID($UID);
		if ($this->profileService->storeNewForcedPassword($UID, $binToken, $this->getParsedPassword()) === 0)
			return ['success' => false, 'errors' => $this->translateServiceErrors()];

		return ['success' => true];
	}

	private function getPasswordPattern(): string
	{
		return $this->config->getConfigValue('password_pattern', 'main');
	}

	private function getParsedPassword(): string
	{
		$password = $this->formInputHandler->getParsed()[Parameters::PARAMETER_PASSWORD] ?? '';

		return is_string($password) ? $password : '';
	}

	/**
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function translateServiceErrors(): array
	{
		$translated = [];
		foreach ($this->profileService->getErrorMessages() as $error)
		{
			$translated[] = $this->translator->translate($error, 'profile');
		}

		return $translated;
	}
}
