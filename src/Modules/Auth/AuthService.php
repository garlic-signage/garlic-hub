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
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Entities\UserEntity;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Services\UsersService;
use DateMalformedStringException;
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
	private string $errorMessage = '';

	public function __construct(private readonly UsersService 		$userService,
								private readonly UserTokenService   $userTokenService,
								private readonly Cookie             $cookie,
								private readonly LoggerInterface    $logger)
	{
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
	 * @return UserEntity|null
	 * @throws DateMalformedStringException
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

		$cookiePayload = $this->cookie->getCookie(self::COOKIE_NAME_AUTO_LOGIN);
		if ($cookiePayload === null)
		{
			$this->logger->error('No token in cookie cookie found.');
			$this->errorMessage = 'No token in cookie cookie found.';
			return null;
		}

		return $this->loginSilent($cookiePayload);
	}

	/**
	 * @param string $cookieToken
	 * @return UserEntity|null
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DateMalformedStringException
	 */
	public function loginSilent(string $cookieToken): ?UserEntity
	{
		$this->logger->info('Attempt silent login.');
		$dbToken = $this->userTokenService->findByToken($cookieToken);
		if ($dbToken === null)
		{
			$this->logger->error('Autologin token not found.');
			$this->errorMessage = 'Autologin token not found.';
			return null;
		}
		if ($dbToken['expires_at'] < new DateTime()->format('Y-m-d H:i:s'))
		{
			$this->logger->error('Autologin expired.');
			$this->errorMessage = 'Autologin expired.';
			$this->userTokenService->deleteExpiredToken();
			return null;
		}
		if ($dbToken['used_at']  !== null)
		{
			$this->logger->error('Cookie manipulation. Contact administrator');
			$this->errorMessage = 'Cookie manipulation. Contact administrator.';
			$this->userTokenService->deleteAllUserTokens($dbToken['UID']);
			return null;
		}
		$this->userTokenService->useToken($cookieToken);


		// rotate Autologin
		$this->createAutologinCookie($dbToken['UID']);

		$userEntity = $this->getCurrentUser($dbToken['UID']);
		$this->validateUserStatus($userEntity->getMain()['status']);
		if (!empty($this->errorMessage))
			return null;

		$this->userService->updateUserStats($dbToken['UID']);
		return $userEntity;
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Exception
	 */
	public function createAutologinCookie(int $UID): void
	{
		// check if there is another
		$token = $this->userTokenService->generateToken();

		$this->userTokenService->insertToken($UID, $token, TokenPurposes::AUTOLOGIN);

		$this->cookie->createCookie(
			self::COOKIE_NAME_AUTO_LOGIN,
			$token,
			new DateTime(UserTokenService::AUTOLOGIN_EXPIRE)
		);
	}

	/**
	 * @param array<string,mixed> $user
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function logout(array $user): void
	{
		$this->logger->info('logout for user: '.$user['UID'].': '.$user['username']);
		$this->userService->invalidateCache($user['UID']);
		$token = $this->cookie->getCookie(AuthService::COOKIE_NAME_AUTO_LOGIN);
		if ($token !== null)
			$this->userTokenService->deleteByToken($token);

		$this->cookie->deleteCookie(AuthService::COOKIE_NAME_AUTO_LOGIN);
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