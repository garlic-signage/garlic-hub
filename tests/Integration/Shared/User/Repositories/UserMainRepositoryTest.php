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

namespace Tests\Integration\Shared\User\Repositories;

use App\Shared\User\Repositories\Edge\UserMainRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class UserMainRepositoryTest extends RepositoryTestCase
{
	private UserMainRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new UserMainRepository($this->connection);
		$this->loadFixture('auth/users.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdDoesNotReturnTheSensitiveColumns(): void
	{
		$rows = $this->repository->findById(10);

		static::assertCount(1, $rows);
		static::assertSame(['UID', 'company_id', 'status', 'locale', 'email', 'username'], array_keys($rows[0]));
		static::assertSame('regular', $rows[0]['username']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdSecuredForUnknownUser(): void
	{
		$user = $this->repository->findByIdSecured(10);

		static::assertSame([], $this->repository->findByIdSecured(999));
		static::assertNotSame([], $user);
		static::assertSame('de_DE', $user['locale']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdentifierByUsernameIgnoresCase(): void
	{
		$user = $this->repository->findByIdentifier('REGULAR');

		static::assertSame(10, (int) $user['UID']);
		static::assertStringStartsWith('$2y$', $user['password']);
		static::assertSame(['UID', 'password', 'locale', 'status', 'company_id'], array_keys($user));
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdentifierByEmailIgnoresCase(): void
	{
		static::assertSame(10, (int) $this->repository->findByIdentifier('regular@example.com')['UID']);
		static::assertSame(14, (int) $this->repository->findByIdentifier('OTHER@example.com')['UID']);
	}

	/**
	 * An email address is never matched against usernames and the other way round.
	 *
	 * @throws Exception
	 */
	public function testFindByIdentifierDoesNotMixUsernameAndEmail(): void
	{
		static::assertSame([], $this->repository->findByIdentifier('regular@example.org'));
		static::assertSame([], $this->repository->findByIdentifier('nobody'));
		static::assertSame([], $this->repository->findByIdentifier(''));
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdentifierTreatsSqlAsPlainText(): void
	{
		static::assertSame([], $this->repository->findByIdentifier("x' OR '1'='1"));
		static::assertSame([], $this->repository->findByIdentifier("x@example.com' OR '1'='1"));
	}

	/**
	 * @throws Exception
	 */
	public function testFindExistingUserFindsByUsernameOrEmail(): void
	{
		$byName  = $this->repository->findExistingUser('regular', 'new@example.com');
		$byEmail = $this->repository->findExistingUser('newname', 'other@example.com');

		static::assertSame([10], array_map('intval', array_column($byName, 'UID')));
		static::assertSame([14], array_map('intval', array_column($byEmail, 'UID')));
		static::assertSame([], $this->repository->findExistingUser('newname', 'new@example.com'));
	}
}
