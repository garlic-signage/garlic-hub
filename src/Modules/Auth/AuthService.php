<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Auth;

use App\Framework\Exceptions\AuthException;
use App\Framework\Exceptions\FrameworkException;
use App\Shared\User\UserStatus;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\User\TwoFactor\TwoFactorService;
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use DateMalformedStringException;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

/**
 * Authentication service responsible for managing user authentication,
 * autologin, and associated functionality using a user service,
 * cookies, and logging.
 */
class AuthService
{
	// the audit log shows what was typed in: nobody fills it with huge strings
	private const int MAX_LOGGED_IDENTIFIER = 64;
	// of a name which is no user, maybe a password in the wrong field, the log of the application gets the beginning
	private const int LOGGED_UNKNOWN_PREFIX = 3;
	private string $errorMessage = '';

	public function __construct(private readonly UsersService 		$userService,
								private readonly AutoLoginService   $autoLoginService,
								private readonly LoggerInterface    $logger,
								private readonly LoginThrottle      $loginThrottle,
								private readonly AuditLog           $auditLog,
								private readonly TwoFactorService   $twoFactorService)
	{}

	public function getErrorMessage(): string
	{
		return $this->errorMessage;
	}

	/**
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function login(string $identifier, string $password): ?UserEntity
	{
		// the service lives for the whole request: a failed login by cookie before must not stop this one
		$this->errorMessage = '';
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$userData = $this->userService->findUser($identifier);
		if ($this->loginThrottle->isBlocked($identifier, $ip))
		{
			$this->errorMessage = 'Too many failed login attempts. Please try again later.';
			$this->logger->warning('Login blocked', ['username' => self::loggableIdentifier($identifier, $userData !== []), 'ip_address' => $ip]);
			return null;
		}

		// a user created by an admin has no password (NULL) until he sets one: he can not log in, like an unknown user
		$hash = $userData['password'] ?? '';
		if (!is_string($hash) || $hash === '')
		{
			// hashing costs as much as verifying, so the response time does not reveal whether the user exists
			password_hash($password, PASSWORD_DEFAULT);
		}
		if (!is_string($hash) || $hash === '' || !password_verify($password, $hash))
		{
			$this->loginThrottle->registerFailure($identifier, $ip);
			$this->errorMessage = 'Invalid credentials.';
			$this->logger->error('Login failed', [
				'username' => self::loggableIdentifier($identifier, $userData !== []),
				'ip_address' => $ip,
				'time' => date('Y-m-d H:i:s'),
				'reason' => 'Invalid credentials.',
			]);
			$this->recordFailedLogin($identifier, $ip, isset($userData['UID']) ? (int) $userData['UID'] : null);
			return null;
		}
		$this->validateUserStatus($userData['status']);
		if (!empty($this->errorMessage))
			return null;

		$this->loginThrottle->reset($identifier, $ip);
		$this->rehashPasswordIfOutdated($userData['UID'], $password, $userData['password']);
		$this->userService->invalidateCache($userData['UID']);
		$this->logger->info('Invalidate user cache for: '. $identifier. ' with id:'. $userData['UID']);

		$entity =  $this->getCurrentUser($userData['UID']);

		$this->logger->info('Entity created for: '. implode('|', $entity->getMain()));

		return $entity;
	}

	/**
	 * Whether the login needs the second step: the user turned the second factor on.
	 *
	 * @throws Exception
	 */
	public function needsSecondFactor(int $UID): bool
	{
		return $this->twoFactorService->isEnabled($UID);
	}

	/**
	 * The second step of the login, after the password: a code of the app or a recovery code.
	 * The failures are counted per user and IP address and per user (LoginThrottle::isSecondFactorBlocked()).
	 *
	 * @throws CryptoException
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	public function loginWithSecondFactor(int $UID, string $code): ?UserEntity
	{
		$this->errorMessage = '';
		$ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		if ($this->loginThrottle->isSecondFactorBlocked($UID, $ip))
		{
			$this->errorMessage = 'two_factor_blocked';
			$this->logger->warning('Second factor blocked', ['UID' => $UID, 'ip_address' => $ip]);
			return null;
		}

		if (!$this->twoFactorService->verify($UID, $code))
		{
			$locked = $this->loginThrottle->registerSecondFactorFailure($UID, $ip);
			$this->errorMessage = $this->loginThrottle->isSecondFactorBlocked($UID, $ip) ? 'two_factor_blocked' : 'two_factor_wrong';
			$this->logger->error('Second factor failed', ['UID' => $UID, 'ip_address' => $ip]);
			$this->recordFailedSecondFactor($UID, $ip, $locked);
			return null;
		}

		$this->loginThrottle->resetSecondFactor($UID, $ip);
		// an admin may have blocked the user since the password was checked
		$entity = $this->getCurrentUser($UID);
		$this->validateUserStatus((int) $entity->getMain()['status']);

		return $this->errorMessage === '' ? $entity : null;
	}

	/**
	 * The login by the provider of the SSO, which confirmed the user: only his status counts here.
	 * The provider asks for the second factor, so there is none of ours.
	 *
	 * @throws Exception
	 */
	public function loginBySso(int $UID): ?UserEntity
	{
		$this->errorMessage = '';
		$this->userService->invalidateCache($UID);
		$entity = $this->getCurrentUser($UID);
		$this->validateUserStatus((int) $entity->getMain()['status']);
		if ($this->errorMessage !== '')
			return null;

		$this->logger->info('SSO login for user ' . $UID);

		return $entity;
	}

	private function recordFailedSecondFactor(int $UID, string $ip, bool $locked): void
	{
		try
		{
			$this->auditLog->record(0, 'auth', AuditAction::TWO_FACTOR_FAILED, null, $UID, ['ip_address' => $ip]);
			if ($locked)
				$this->auditLog->record(0, 'auth', AuditAction::TWO_FACTOR_LOCKED, null, $UID, ['ip_address' => $ip]);
		}
		catch (Throwable $e)
		{
			$this->logger->error('Failed second factor could not be audited: ' . $e->getMessage());
		}
	}

	/**
	 * What the log of the application gets of a typed user name. A name which is no user may be a password typed
	 * into the wrong field: only its beginning is logged. The audit log keeps more, see recordFailedLogin().
	 */
	private static function loggableIdentifier(string $identifier, bool $isUser): string
	{
		if ($isUser)
			return mb_substr($identifier, 0, self::MAX_LOGGED_IDENTIFIER);

		return mb_strlen($identifier) <= self::LOGGED_UNKNOWN_PREFIX ? $identifier : mb_substr($identifier, 0, self::LOGGED_UNKNOWN_PREFIX) . '…';
	}

	/**
	 * A failure of the audit log must not turn a wrong password into an error page.
	 */
	private function recordFailedLogin(string $identifier, string $ip, ?int $UID): void
	{
		try
		{
			$this->auditLog->record(0, 'auth', AuditAction::LOGIN_FAILED, null, $UID, ['username' => mb_substr($identifier, 0, self::MAX_LOGGED_IDENTIFIER), 'ip_address' => $ip]);
		}
		catch (Throwable $e)
		{
			$this->logger->error('Failed login could not be audited: ' . $e->getMessage());
		}
	}

	/**
	 * A user with a hash of an outdated algorithm or cost gets a new one with the login,
	 * the only moment where the clear text password is known. A failure must not prevent the login.
	 */
	private function rehashPasswordIfOutdated(int $UID, string $password, string $hash): void
	{
		if (!password_needs_rehash($hash, PASSWORD_DEFAULT))
			return;

		try
		{
			$this->userService->updatePasswordHash($UID, password_hash($password, PASSWORD_DEFAULT));
		}
		catch (Exception $e)
		{
			$this->logger->warning('Password hash could not be renewed for UID ' . $UID . ': ' . $e->getMessage());
		}
	}

	/**
	 * @return UserEntity|null
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function loginByCookie(): ?UserEntity
	{
		$this->errorMessage = '';
		try
		{
			$cookieToken = $this->autoLoginService->loadTokenFromCookie();
			$UID         = $this->autoLoginService->loginSilent($cookieToken);

			$userEntity = $this->getCurrentUser($UID);
			$this->validateUserStatus($userEntity->getMain()['status']);
			if (!empty($this->errorMessage))
			{
				// a deleted, blocked or not verified user must not get in with an old cookie either
				$this->logger->warning('Autologin refused for user ' . $UID . ': ' . $this->errorMessage);
				$this->autoLoginService->removeAutoLogin();
				return null;
			}

			$this->userService->updateUserStats($UID);
			return $userEntity;
		}
		catch (AuthException $e)
		{
			$this->logger->error($e->getMessage());
			$this->errorMessage = $e->getMessage();
			return null;
		}
	}


	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Exception
	 */
	public function createAutologin(int $UID): void
	{
		$this->autoLoginService->createAutologinCookie($UID);
	}

	/**
	 * @param array<string,mixed> $user
	 * @throws Exception
	 */
	public function logout(array $user): void
	{
		$this->logger->info('logout for user: '.$user['UID'].': '.$user['username']);
		$this->userService->invalidateCache($user['UID']);
		$this->autoLoginService->removeAutoLogin();
	}

	/**
	 * @throws Exception
	 */
	public function getCurrentUser(int $UID): UserEntity
	{
		return $this->userService->getUserById($UID);
	}

	/**
	 * Whether the user of a running session may still work: an admin may have blocked or deleted him since the login,
	 * or the password was changed (by the user in another browser or reset by an admin) after the session started.
	 * The user comes from the cache, which is invalidated when an admin changes the status or the password changes.
	 *
	 * @throws Exception
	 */
	public function isUserActive(int $UID, ?string $passwordChangeOfSession): bool
	{
		$main   = $this->getCurrentUser($UID)->getMain();
		$status = $main['status'] ?? null;
		if (!is_numeric($status))
			return false;

		if (in_array((int) $status, [UserStatus::DELETED->value, UserStatus::BLOCKED->value, UserStatus::NOT_VERIFIED->value], true))
			return false;

		return ($main['last_password_change'] ?? null) === $passwordChangeOfSession;
	}

	private function validateUserStatus(int $status): void
	{
		$this->logger->info('User status is: '. $status);

		switch ($status)
		{
			case UserStatus::DELETED->value:
				$this->errorMessage = 'login//account_deleted';
				break;

			case UserStatus::BLOCKED->value:
				$this->errorMessage = 'login//account_locked';
				break;

			case UserStatus::NOT_VERIFIED->value:
				$this->errorMessage = 'login//account_inactive';
				break;
		}
	}

}