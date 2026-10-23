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
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Authentication service responsible for managing user authentication,
 * autologin, and associated functionality using a user service,
 * cookies, and logging.
 */
class AuthService
{
	private string $errorMessage = '';

	public function __construct(private readonly UsersService 		$userService,
								private readonly AutoLoginService   $autoLoginService,
								private readonly LoggerInterface    $logger,
								private readonly LoginThrottle      $loginThrottle)
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
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$this->logger->info('Login attempt from: '. $identifier);
		if ($this->loginThrottle->isBlocked($identifier, $ip))
		{
			$this->errorMessage = 'Too many failed login attempts. Please try again later.';
			$this->logger->warning('Login blocked', ['username' => $identifier, 'ip_address' => $ip]);
			return null;
		}

		$userData = $this->userService->findUser($identifier);
		if ($userData === [])
		{
			// hashing costs as much as verifying, so the response time does not reveal whether the user exists
			password_hash($password, PASSWORD_DEFAULT);
		}
		if ($userData === [] || !password_verify($password, $userData['password']))
		{
			$this->loginThrottle->registerFailure($identifier, $ip);
			$this->errorMessage = 'Invalid credentials.';
			$this->logger->error('Login failed', [
				'username' => $identifier,
				'ip_address' => $ip,
				'time' => date('Y-m-d H:i:s'),
				'reason' => 'Invalid credentials.',
			]);
			return null;
		}
		$this->validateUserStatus($userData['status']);
		if (!empty($this->errorMessage))
			return null;

		$this->loginThrottle->reset($identifier, $ip);
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
	 */
	public function loginByCookie(): ?UserEntity
	{
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