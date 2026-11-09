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

namespace App\Modules\Users\Services;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Users\UserStatus;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\User\Repositories\Core\UserMainRepository;
use App\Shared\User\Repositories\Core\UserSsoRepository;
use App\Shared\User\Sso\SsoIdentity;
use App\Shared\User\Sso\SsoRefusedException;
use App\Shared\User\Sso\SsoUserProvisioning;
use Throwable;

/**
 * The user of a login by SSO. A known identity is its user. Otherwise an account with the same email is linked,
 * of the company of the provider, but only when the provider verified the email: an email which anybody
 * may enter at the provider would take over the account. Without one the first login creates the user in
 * the company of the provider, without rights and without password, with his media folder.
 * The admin grants the rights later.
 */
class SsoUserProvisioningService implements SsoUserProvisioning
{
	private const string MODULE_NAME = 'users';
	private const int MAX_USERNAME_LENGTH = 50;

	public function __construct(
		private readonly UserSsoRepository $userSsoRepository,
		private readonly UserMainRepository $userMainRepository,
		private readonly NodesService $nodesService,
		private readonly Transactions $transactions,
		private readonly AuditLog $auditLog
	) {}

	/**
	 * @throws Throwable
	 */
	public function provide(SsoIdentity $identity): int
	{
		$UID = $this->userSsoRepository->findUID($identity->provider->id, $identity->subject);
		if ($UID !== null)
			return $UID;

		if (filter_var($identity->email, FILTER_VALIDATE_EMAIL) === false)
			throw new SsoRefusedException('sso_no_email');

		$this->transactions->begin();
		try
		{
			$existing = $this->userMainRepository->findByIdentifier($identity->email);
			if ($existing !== [])
			{
				// the provider of one company does not log in the user of another one
				if (!$identity->emailVerified || (int) ($existing['company_id'] ?? 0) !== $identity->provider->companyId)
					throw new SsoRefusedException('sso_account_exists');

				// an account with a password logs in by it: whoever has the email at the provider must not take it
				// over (an admin switches it to SSO, which removes the password). An account with an identity at
				// this provider already is someone else: an email given anew to the next employee.
				$hash = $existing['password'] ?? null;
				if ((is_string($hash) && $hash !== '') || $this->userSsoRepository->hasLink((int) $existing['UID'], $identity->provider->id))
					throw new SsoRefusedException('sso_account_exists');

				$UID = (int) $existing['UID'];
			}
			else
				$UID = $this->createUser($identity);

			$this->userSsoRepository->link($UID, $identity->provider->id, $identity->subject);
			$this->transactions->commit();

			return $UID;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * @throws Throwable
	 */
	private function createUser(SsoIdentity $identity): int
	{
		$username = $this->uniqueUsername($identity->email);
		$UID      = (int) $this->userMainRepository->insert([
			'company_id' => $identity->provider->companyId,
			'username'   => $username,
			'email'      => $identity->email,
			'status'     => UserStatus::REGISTERED->value
		]);
		if ($UID === 0)
			throw new ModuleException(self::MODULE_NAME, 'Insert failed.');

		$this->nodesService->addUserDirectory($UID, $username);
		$this->auditLog->record($UID, self::MODULE_NAME, AuditAction::USER_CREATED, null, $UID, ['username' => $username, 'sso' => $identity->provider->name]);

		return $UID;
	}

	/**
	 * The part of the email before the @, with a number when somebody has it already.
	 *
	 * @throws \Doctrine\DBAL\Exception
	 */
	private function uniqueUsername(string $email): string
	{
		$base = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($email, '@', true) ?: '')) ?? '';
		$base = mb_substr($base !== '' ? $base : 'user', 0, self::MAX_USERNAME_LENGTH - 4);

		$candidate = $base;
		for ($number = 2; $this->usernameExists($candidate); $number++)
			$candidate = $base . $number;

		return $candidate;
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	private function usernameExists(string $username): bool
	{
		foreach ($this->userMainRepository->findExistingUser($username, $username) as $existing)
		{
			if (is_array($existing) && strcasecmp((string) ($existing['username'] ?? ''), $username) === 0)
				return true;
		}

		return false;
	}
}
