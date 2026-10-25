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

namespace Tests\Unit\Modules\Account\Services;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Security\Crypt;
use App\Modules\Account\Services\AccountService;
use App\Modules\Users\UserStatus;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Shared\User\UserTokenService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * The service with a real database is tested in tests/Integration/Modules/Account/Services/PasswordTest.
 * Here are the two ways out of the forced password change which a real database does not produce:
 * the user is gone after the password was set, and the status can not be changed.
 *
 * The stubs write a row into a real transaction for the steps they stand for. The service has to take
 * these rows back when a later step fails.
 */
class AccountServiceTest extends TestCase
{
	private Connection $connection;
	private UserMainRepository&Stub $userMainRepository;
	private UserTokenService&Stub $userTokenService;
	private AccountService $accountService;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
		$this->connection->executeStatement('CREATE TABLE steps (name TEXT)');

		$crypt = $this->createStub(Crypt::class);
		$crypt->method('createPasswordHash')->willReturn('hashed');

		$this->userMainRepository = $this->createStub(UserMainRepository::class);
		$this->userTokenService   = $this->createStub(UserTokenService::class);
		$this->userTokenService->method('useToken')->willReturnCallback(fn(): int => $this->step('token'));

		$this->accountService = new AccountService($this->userMainRepository, $this->userTokenService, $crypt, new Transactions($this->connection));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	private function step(string $name): int
	{
		return (int) $this->connection->executeStatement('INSERT INTO steps (name) VALUES (?)', [$name]);
	}

	/**
	 * @return list<string>
	 * @throws \Doctrine\DBAL\Exception
	 */
	private function steps(): array
	{
		return $this->connection->fetchFirstColumn('SELECT name FROM steps ORDER BY rowid');
	}

	/**
	 * @param array<string,mixed> $user the user the repository finds, [] for none
	 * @param int $statusUpdate result of the update of the status
	 */
	private function prepareRepository(array $user, int $statusUpdate): void
	{
		$this->userMainRepository->method('update')->willReturnCallback(
			fn(int $UID, array $data): int => isset($data['password']) ? $this->step('password') : $statusUpdate
		);
		$this->userMainRepository->method('findByIdSecured')->willReturn($user);
	}

	/**
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testAUserWhoIsGoneAfterTheNewPasswordTakesBackAllSteps(): void
	{
		$this->prepareRepository([], 1);

		try
		{
			$this->accountService->storeNewForcedPassword(5, 'token', 'New-Pass1!');
			static::fail('Expected a NotFoundException');
		}
		catch (NotFoundException $e)
		{
			static::assertSame('user_not_found', $e->getTranslationKey());
		}

		static::assertSame([], $this->steps());
		static::assertFalse($this->connection->isTransactionActive());
	}

	/**
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testAStatusWhichCanNotBeChangedTakesBackPasswordAndToken(): void
	{
		$this->prepareRepository(['status' => UserStatus::NOT_VERIFIED->value], 0);

		try
		{
			$this->accountService->storeNewForcedPassword(5, 'token', 'New-Pass1!');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('status_update_failed', $e->getTranslationKey());
		}

		static::assertSame([], $this->steps());
		static::assertFalse($this->connection->isTransactionActive());
	}

	/**
	 * Control: the same steps are kept when everything works.
	 *
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testAllStepsAreKeptWhenTheStatusIsChanged(): void
	{
		$this->prepareRepository(['status' => UserStatus::NOT_VERIFIED->value], 1);

		$this->accountService->storeNewForcedPassword(5, 'token', 'New-Pass1!');

		static::assertSame(['password', 'token'], $this->steps());
		static::assertFalse($this->connection->isTransactionActive());
	}
}
