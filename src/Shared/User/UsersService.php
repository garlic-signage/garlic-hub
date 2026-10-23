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

namespace App\Shared\User;

use App\Framework\Services\AbstractBaseService;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Shared\User\Repositories\Edge\UserTokensRepository;
use App\Shared\User\Repositories\UserRepositoryFactory;
use App\Framework\Exceptions\CoreException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheException;

/**
 * User service handles and caches the userEntity. This is a central point for authentication and checking
 *  user access rights for different modules.
 *
 * The class is user agnostic and the methods need user id to work and return a userEntity.
 */
class UsersService extends AbstractBaseService
{
	const int USER_STATUS_DELETED       = 0;
	const int USER_STATUS_LOCKED        = 1;
	const int USER_STATUS_REGISTERED    = 2;
	const int USER_STATUS_REGULAR       = 3;
/*	const int USER_STATUS_PREMIUM_A     = 4;
	const int USER_STATUS_PREMIUM_B     = 5;
	const int USER_STATUS_PREMIUM_C     = 6;
	const int USER_STATUS_PREMIUM_D     = 7;
	const int USER_STATUS_PREMIUM_E     = 8;
	const int USER_STATUS_ADMIN         = 9;
*/
	private UserEntityFactory $userEntityFactory;
	private UserRepositoryFactory $userRepositoryFactory;

	private Psr16Adapter $cache;
	/** @var array<int,UserEntity> users already loaded in this request, every ACL check asks for them */
	private array $loadedUsers = [];
	/** @var array{
	 *     main: UserMainRepository,
	 *     tokens: UserTokensRepository,
	 *     acl: UserAclRepository
	 *	 }  */
	private array $userRepositories;

	public function __construct(UserRepositoryFactory $userRepositoryFactory, UserEntityFactory $userEntityFactory, Psr16Adapter $cache, LoggerInterface $logger)
	{
		$this->userRepositoryFactory = $userRepositoryFactory;
		$this->userEntityFactory     = $userEntityFactory;
		$this->cache                 = $cache;
		$this->userRepositories      = $this->userRepositoryFactory->create();
		parent::__construct($logger);
	}

	/**
	 * @throws Exception
	 */
	public function updateUserStats(int $UID): int
	{
		return $this->userRepositories['main']->registerLogin($UID);
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function findUser(string $identifier): array
	{
		$usrMainRepository = $this->userRepositories['main'];

		return $usrMainRepository->findByIdentifier($identifier);
	}

	/**
	 * Get the current user from a cache or database.
	 * A failing cache is logged, and the user is loaded from the database.
	 *
	 * @throws Exception
	 */
	public function getUserById(int $UID): UserEntity
	{
		if (isset($this->loadedUsers[$UID]))
			return $this->loadedUsers[$UID];

		$cacheKey   = $this->getCacheKey($UID);
		$cachedData = $this->readFromCache($cacheKey);

		if ($cachedData)
			return $this->loadedUsers[$UID] = $this->userEntityFactory->create($cachedData);

		$userData = $this->collectUserData($UID);

		try
		{
			$this->cache->set($cacheKey, $userData, 3600 * 24); // Cache for 1 day
		}
		catch (CacheException $e)
		{
			$this->logger->warning('User cache not writable: ' . $e->getMessage());
		}

		return $this->loadedUsers[$UID] = $this->userEntityFactory->create($userData);
	}

	/**
	 * Must not fail silently, otherwise changed rights would stay in the cache for up to one day.
	 *
	 * @throws CoreException
	 */
	public function invalidateCache(int $UID): void
	{
		unset($this->loadedUsers[$UID]);
		try
		{
			$this->cache->delete($this->getCacheKey($UID));
		}
		catch (CacheException|InvalidArgumentException $e)
		{
			$previous = $e instanceof \Exception ? $e : null;
			throw new CoreException('User cache for UID ' . $UID . ' could not be invalidated.', 0, $previous);
		}
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function readFromCache(string $cacheKey): ?array
	{
		try
		{
			$data = $this->cache->get($cacheKey);
			return is_array($data) ? $data : null;
		}
		catch (CacheException $e)
		{
			$this->logger->warning('User cache not readable: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	private function collectUserData(int $UID): array
	{
		$userData = [];
		foreach ($this->userRepositories as $key => $repository)
		{
			if ($key === 'main')
			{
				/** @var UserMainRepository $repository */
				$userData[$key] = $repository->findByIdSecured($UID);
			}
			elseif ($key === 'acl')
			{
				// because there can be more than one result
				/** @var UserAclRepository $repository */
				$userData[$key] = $repository->findById($UID);
			}
			else
			{
				/** @var UserTokensRepository $repository */
				$userData[$key] = $repository->findFirstById($UID);
			}
		}

		return $userData;
	}

	private function getCacheKey(int $UID): string
	{
		return "user_$UID";
	}
}