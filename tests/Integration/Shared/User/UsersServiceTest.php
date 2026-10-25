<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Shared\User;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Shared\User\Repositories\UserRepositoryFactory;
use App\Shared\User\UserEntityFactory;
use App\Shared\User\UsersService;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Phpfastcache\Helper\Psr16Adapter;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Support\RecordingLogger;

/**
 * The user with his data and rights, loaded once and cached for a day. Users: see auth/users.sql, 10 regular
 * (with rights for the modules player and playlists), 11 deleted.
 */
#[Group('acl')]
class UsersServiceTest extends ContainerTestCase
{
	private RecordingLogger $logger;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('auth/users.sql');
		$this->logger = new RecordingLogger();
	}

	private function service(?Psr16Adapter $cache = null): UsersService
	{
		return new UsersService(
			new UserRepositoryFactory($this->container->get(Config::class), $this->connection),
			$this->container->get(UserEntityFactory::class),
			$cache ?? $this->container->get(Psr16Adapter::class),
			$this->logger
		);
	}

	private function failingCache(): Psr16Adapter
	{
		$cache = $this->createStub(Psr16Adapter::class);
		$cache->method('get')->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));
		$cache->method('set')->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));
		$cache->method('delete')->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));

		return $cache;
	}

	public function testAUserIsLoadedWithHisDataAndRightsButWithoutPassword(): void
	{
		$user = $this->service()->getUserById(10);

		static::assertSame(['regular', 'Regular@Example.com', 3], [$user->getMain()['username'], $user->getMain()['email'], (int) $user->getMain()['status']]);
		static::assertArrayNotHasKey('password', $user->getMain());
		static::assertEqualsCanonicalizing(['player', 'playlists'], array_column($user->getAcl(), 'module'));
	}

	public function testAnUnknownUserIsAnEntityWithoutData(): void
	{
		$user = $this->service()->getUserById(99);

		static::assertSame([], $user->getMain());
		static::assertSame([], $user->getAcl());
	}

	public function testALoadedUserIsNotLoadedAgainInTheSameRequest(): void
	{
		$service = $this->service();
		$first   = $service->getUserById(10);
		$this->connection->executeStatement("UPDATE user_main SET username = 'changed' WHERE UID = 10");

		static::assertSame($first, $service->getUserById(10));
	}

	public function testTheCacheServesTheUserToTheNextRequestUntilItIsInvalidated(): void
	{
		$this->service()->getUserById(10);
		$this->connection->executeStatement("UPDATE user_main SET username = 'changed' WHERE UID = 10");

		// another request: a new service, the same cache
		$next = $this->service();
		static::assertSame('regular', $next->getUserById(10)->getMain()['username']);

		$other = $this->service();
		$other->invalidateCache(10);
		static::assertSame('changed', $other->getUserById(10)->getMain()['username']);
	}

	public function testTheInvalidationReachesTheUserInTheSameRequest(): void
	{
		$service = $this->service();
		$service->getUserById(10);
		$this->connection->executeStatement("UPDATE user_main SET username = 'changed' WHERE UID = 10");

		$service->invalidateCache(10);

		static::assertSame('changed', $service->getUserById(10)->getMain()['username']);
	}

	public function testAnUnreadableAndUnwritableCacheIsLoggedAndTheUserIsLoadedFromTheDatabase(): void
	{
		$user = $this->service($this->failingCache())->getUserById(10);

		static::assertSame('regular', $user->getMain()['username']);
		static::assertCount(2, $this->logger->messages['warning']);
		static::assertStringContainsString('not readable', $this->logger->messages['warning'][0]);
		static::assertStringContainsString('not writable', $this->logger->messages['warning'][1]);
	}

	public function testAnInvalidationWhichFailsIsAnError(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('User cache for UID 10 could not be invalidated.');

		$this->service($this->failingCache())->invalidateCache(10);
	}

	public function testALoginIsCountedAndAPasswordHashIsStored(): void
	{
		$service = $this->service();

		static::assertSame(1, $service->updateUserStats(10));
		static::assertSame(1, $service->updatePasswordHash(10, 'new-hash'));

		static::assertSame([1, 'new-hash'], [(int) $this->connection->fetchOne('SELECT num_logins FROM user_main WHERE UID = 10'), $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = 10')]);
		static::assertSame(0, $service->updateUserStats(99));
	}

	public function testAUserIsFoundByUsernameOrEmail(): void
	{
		$service = $this->service();

		static::assertSame(10, (int) $service->findUser('regular')['UID']);
		static::assertSame(10, (int) $service->findUser('regular@example.com')['UID']);
		static::assertSame([], $service->findUser('nobody'));
	}
}
