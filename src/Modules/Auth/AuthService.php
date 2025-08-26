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

use App\Framework\Core\Cookie;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Profile\Entities\UserEntity;
use App\Modules\Users\Services\UsersService;
use DateTime;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Authentication service responsible for managing user authentication,
 * autologin, and associated functionality using a user service,
 * cookies, and logging.
 */
class AuthService
{
	public const string COOKIE_NAME_AUTO_LOGIN = 'UserLogin';
	public const string AUTOLOGIN_EXPIRE = '+28 days';
	private UsersService $userService;
	private Cookie $cookie;
	private string $errorMessage = '';
	private LoggerInterface $logger;

	public function __construct(UsersService $userService, Cookie $cookie, LoggerInterface $logger)
	{
		$this->userService = $userService;
		$this->cookie      = $cookie;
		$this->logger      = $logger;
	}

	public function getErrorMessage(): string
	{
		return $this->errorMessage;
	}

	/**
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function login(string $identifier, string $password): ?UserEntity
	{
		$userData = $this->userService->findUser($identifier);
		$this->logger->info('Login attempt from: '. $identifier);
		if ($userData === [] || !password_verify($password, $userData['password']))
		{
			$this->errorMessage = 'Invalid credentials.';
			$this->logger->error('Login failed', [
				'username' => $identifier,
				'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
				'time' => date('Y-m-d H:i:s'),
				'reason' => 'Invalid credentials.',
			]);
			return null;
		}
		$this->validateUserStatus($userData['status']);
		if (!empty($this->errorMessage))
			return null;

		$this->userService->invalidateCache($userData['UID']);
		$this->logger->info('Invalidate user cache for: '. $identifier. ' with id:'. $userData['UID']);

		$entity =  $this->getCurrentUser($userData['UID']);

		$this->logger->info('Entity created for: '. implode('|', $entity->getMain()));

		return $entity;
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function loginByCookie(): ?UserEntity
	{
		// no cookie? that's it
		if (!$this->cookie->hasCookie(self::COOKIE_NAME_AUTO_LOGIN))
		{
			$this->logger->error('No cookie for autologin was found.');
			$this->errorMessage = 'No cookie for autologin was found.';
			return null;
		}

		/** @var array{UID: int, sid: string } $cookie_payload */
		$cookie_payload = $this->cookie->getHashedCookie(self::COOKIE_NAME_AUTO_LOGIN);
		$UID =  (int) $cookie_payload['UID'];
		if ($UID < 1)
		{
			$this->logger->error('No valid UID found after cookie login.');
			$this->errorMessage = 'No valid UID found after cookie login.';
			return null;
		}

		return $this->loginSilent($UID, $cookie_payload['sid']);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function loginSilent(int $UID, string $sessionId): ?UserEntity
	{
		$this->logger->error('Attempt silent login.');
		$userEntity = $this->getCurrentUser($UID);
		$this->validateUserStatus($userEntity->getMain()['status']);
		if (!empty($this->errorMessage))
			return null;

		$this->userService->updateUserStats($UID, $sessionId);
		return $userEntity;
	}

	/**
	 * @throws FrameworkException
	 */
	public function createAutologinCookie(int $UID, string $sessionId): void
	{
		$payload = ['UID' => (string) $UID, 'sid' => $sessionId];
		$this->cookie->createHashedCookie(self::COOKIE_NAME_AUTO_LOGIN, $payload, new DateTime(self::AUTOLOGIN_EXPIRE));
	}

	/**
	 * @param array<string,mixed> $user
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function logout(array $user): void
	{
		$this->logger->info('logout for user: '.$user['UID'].': '.$user['username']);
		$this->userService->invalidateCache($user['UID']);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function getCurrentUser(int $UID): UserEntity
	{
		return $this->userService->getUserById($UID);
	}

	private function validateUserStatus(int $status): void
	{
		$this->logger->info('User status is: '. $status);

		switch ($status)
		{
			case UsersService::USER_STATUS_DELETED:
				$this->errorMessage = 'login//account_deleted';
				break;

			case UsersService::USER_STATUS_LOCKED:
				$this->errorMessage = 'login//account_locked';
				break;

			case UsersService::USER_STATUS_REGISTERED:
				$this->errorMessage = 'login//account_inactive';
				break;
		}
	}

}