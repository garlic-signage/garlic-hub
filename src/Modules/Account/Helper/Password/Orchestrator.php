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

namespace App\Modules\Account\Helper\Password;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Shared\User\TokenPurposes;
use App\Modules\Account\Services\AccountService;
use App\Shared\User\UserTokenService;
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
		private AccountService      $accountService,
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
		$binToken = $this->decodeToken($passwordToken);
		if ($binToken === null)
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
		if ($this->accountService->updatePassword($UID, $this->getParsedPassword()) === 0)
			return ['success' => false, 'errors' => [$this->translator->translate('password_update_failed', 'account')]];

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

		$binToken = $this->decodeToken($passwordToken);
		if ($binToken === null)
			return ['success' => false, 'errors' => [$this->translator->translate('token_error', 'account')]];

		try
		{
			$this->accountService->storeNewForcedPassword($UID, $binToken, $this->getParsedPassword());
		}
		catch (TranslatableModuleException $e)
		{
			// the form is shown again with the error instead of the standard error page
			return ['success' => false, 'errors' => [$this->translator->translateException($e)]];
		}

		return ['success' => true];
	}

	/**
	 * The token of a link is a hex string, anything else (or an odd length) is no token.
	 */
	private function decodeToken(string $passwordToken): ?string
	{
		if (!ctype_xdigit($passwordToken) || strlen($passwordToken) % 2 !== 0)
			return null;

		$binToken = hex2bin($passwordToken);

		return $binToken === false ? null : $binToken;
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
}
