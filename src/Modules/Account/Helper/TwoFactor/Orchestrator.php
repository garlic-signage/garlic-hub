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
use App\Modules\Auth\UserSession;
use App\Shared\User\TwoFactor\TwoFactorService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
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
		private CsrfToken $csrfToken
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
	 * @throws CryptoException
	 * @throws Exception
	 * @throws Throwable
	 * @throws UserException
	 */
	public function confirm(string $code): bool
	{
		$codes = $this->twoFactorService->confirmSetup($this->userSession->getUID(), $code);
		if ($codes === null)
			return false;

		$this->session->set(self::NEW_CODES, ['codes' => $codes]);
		$this->userSession->setTwoFactor(true);

		return true;
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws UserException
	 */
	public function createRecoveryCodes(): void
	{
		$codes = $this->twoFactorService->createRecoveryCodes($this->userSession->getUID());
		if ($codes !== [])
			$this->session->set(self::NEW_CODES, ['codes' => $codes]);
	}

	/**
	 * @throws CryptoException
	 * @throws Exception
	 * @throws Throwable
	 * @throws UserException
	 */
	public function disable(string $code): bool
	{
		if ($this->isRequired() || !$this->twoFactorService->disable($this->userSession->getUID(), $code))
			return false;

		$this->userSession->setTwoFactor(false);

		return true;
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
}
