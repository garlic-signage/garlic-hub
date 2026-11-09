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

namespace App\Modules\Account\Helper\TwoFactor;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Modules\Auth\LoginThrottle;
use App\Modules\Auth\UserSession;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use App\Shared\User\UsersService;
use App\Shared\User\TwoFactor\TwoFactorService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

/**
 * The tab of the second factor in the account: set it up, new recovery codes, turn it off.
 * New recovery codes are shown once, on the page after the change (they wait in the session until then).
 */
readonly class Orchestrator
{
	private const string NEW_CODES = 'two_factor_new_codes';
	private const string MODULE_NAME = 'account';

	public function __construct(
		private UserSession $userSession,
		private TwoFactorService $twoFactorService,
		private Session $session,
		private Config $config,
		private Translator $translator,
		private CsrfToken $csrfToken,
		private LoginThrottle $loginThrottle,
		private AuditLog $auditLog,
		private UsersService $usersService,
		private UserTokenService $userTokenService
	) {}

	/**
	 * While the admin requires it, the user can not turn it off himself (only the admin resets it).
	 */
	public function isRequired(): bool
	{
		return $this->config->isSwitchOn('required', 'users', 'TwoFactor');
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function buildTab(): array
	{
		$UID     = $this->userSession->getUID();
		$enabled = $this->twoFactorService->isEnabled($UID);

		return [
			'CSRF_TOKEN'  => $this->csrfToken->getToken(),
			'LANG_CODE'   => $this->translate('two_factor_code'),
			'is_required' => $this->isRequired() && !$enabled ? ['LANG_REQUIRED' => $this->translate('two_factor_required')] : [],
			'new_codes'   => $this->takeNewCodes(),
			'setup'       => $enabled ? [] : $this->buildSetup($UID),
			'enabled'     => $enabled ? $this->buildEnabled($UID) : []
		];
	}

	/**
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws Throwable
	 * @throws UserException
	 */
	public function confirm(string $code): bool
	{
		$UID   = $this->userSession->getUID();
		$codes = $this->twoFactorService->confirmSetup($UID, $code);
		if ($codes === null)
			return false;

		// a session or remember-me cookie from before had only the password: it must not get past the new second
		// factor. This session stays.
		$this->userTokenService->deleteByUserPurpose($UID, TokenPurposes::AUTOLOGIN->value);
		$this->userSession->keepAfterPasswordChange($this->usersService->endSessions($UID));

		$this->session->set(self::NEW_CODES, ['codes' => $codes]);
		$this->userSession->setTwoFactor(true);

		return true;
	}

	/**
	 * Wrong codes are limited like in the login and count together with it.
	 *
	 * @return string the key of the error, empty when the new codes are in the session
	 * @throws CryptoException
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws Throwable
	 * @throws UserException
	 */
	public function createRecoveryCodes(string $code): string
	{
		$UID = $this->userSession->getUID();
		$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		if ($this->loginThrottle->isSecondFactorBlocked($UID, $ip))
			return 'two_factor_blocked';

		$codes = $this->twoFactorService->createRecoveryCodes($UID, $code);
		if ($codes === [])
			return $this->failSecondFactor($UID, $ip);

		$this->loginThrottle->resetSecondFactor($UID, $ip);
		$this->session->set(self::NEW_CODES, ['codes' => $codes]);

		return '';
	}

	/**
	 * Wrong codes are limited like in the login and count together with it: a stolen session does not
	 * help to guess a code.
	 *
	 * @return string the key of the error, empty when it is off
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws Throwable
	 * @throws UserException
	 */
	public function disable(string $code): string
	{
		if ($this->isRequired())
			return 'two_factor_wrong_code';

		$UID = $this->userSession->getUID();
		$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		if ($this->loginThrottle->isSecondFactorBlocked($UID, $ip))
			return 'two_factor_blocked';

		if (!$this->twoFactorService->disable($UID, $code))
			return $this->failSecondFactor($UID, $ip);

		$this->loginThrottle->resetSecondFactor($UID, $ip);
		// the other sessions know that he had it: one of them must not keep it when the admin requires it later
		$this->userSession->keepAfterPasswordChange($this->usersService->endSessions($UID));
		$this->userSession->setTwoFactor(false);

		return '';
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	private function buildSetup(int $UID): array
	{
		$setup = $this->twoFactorService->prepareSetup($UID, $this->userSession->getUsername());
		if ($setup === null)
			return [];

		return [
			'LANG_SETUP'  => $this->translate('two_factor_setup'),
			'QR_CODE'     => $setup['qr_code'],
			'LANG_SECRET' => $this->translate('two_factor_secret'),
			// in groups of four, like the apps show it
			'SECRET'      => implode(' ', str_split($setup['secret'], 4)),
			'LANG_ENABLE' => $this->translate('two_factor_enable')
		];
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	private function buildEnabled(int $UID): array
	{
		return [
			'LANG_ENABLED'            => $this->translate('two_factor_enabled'),
			'LANG_RECOVERY_LEFT'      => sprintf($this->translate('two_factor_recovery_left'), $this->twoFactorService->countRecoveryCodes($UID)),
			'LANG_NEW_RECOVERY_CODES' => $this->translate('two_factor_new_recovery_codes'),
			'can_disable'             => $this->isRequired() ? [] : ['LANG_DISABLE' => $this->translate('two_factor_disable')]
		];
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function takeNewCodes(): array
	{
		$stored = $this->session->get(self::NEW_CODES);
		$this->session->delete(self::NEW_CODES);
		$codes = is_array($stored) ? ($stored['codes'] ?? []) : [];
		if (!is_array($codes) || $codes === [])
			return [];

		return [
			'LANG_NEW_CODES' => $this->translate('two_factor_new_codes'),
			'codes'          => array_map(static fn(mixed $code): array => ['CODE' => is_string($code) ? $code : ''], array_values($codes))
		];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function translate(string $key): string
	{
		return $this->translator->translate($key, self::MODULE_NAME);
	}

	/**
	 * @return string the key of the error
	 * @throws InvalidArgumentException
	 */
	private function failSecondFactor(int $UID, string $ip): string
	{
		if ($this->loginThrottle->registerSecondFactorFailure($UID, $ip))
			$this->auditLog->record($UID, self::MODULE_NAME, AuditAction::TWO_FACTOR_LOCKED, null, $UID, ['ip_address' => $ip]);

		return $this->loginThrottle->isSecondFactorBlocked($UID, $ip) ? 'two_factor_blocked' : 'two_factor_wrong_code';
	}
}
