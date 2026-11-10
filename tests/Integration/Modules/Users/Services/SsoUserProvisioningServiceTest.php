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

namespace Tests\Integration\Modules\Users\Services;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Users\Services\SsoUserProvisioningService;
use App\Shared\Audit\AuditLogRepository;
use App\Shared\Audit\DatabaseAuditLog;
use App\Shared\User\Repositories\Core\UserMainRepository;
use App\Shared\User\Repositories\Core\UserSsoRepository;
use App\Shared\User\Sso\SsoIdentity;
use App\Shared\User\Sso\SsoProvider;
use App\Shared\User\Sso\SsoRefusedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The user of a first SSO login, without the provider: the identity comes as the provider confirmed it.
 * Users: 1 admin, 2 anna (anna@example.com), 3 bernd, all of company 1. The provider 1 is of company 1.
 */
#[Group('auth')]
class SsoUserProvisioningServiceTest extends ContainerTestCase
{
	private SsoUserProvisioningService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->connection->insert('sso_providers', ['provider_id' => 1, 'company_id' => 1, 'name' => 'Example', 'issuer' => 'https://idp.example.test', 'client_id' => 'x', 'client_secret' => '']);
		$this->service = new SsoUserProvisioningService(new UserSsoRepository($this->connection), new UserMainRepository($this->connection),
			$this->container->get(NodesService::class), $this->container->get(Transactions::class), new DatabaseAuditLog($this->container->get(AuditLogRepository::class)));
	}

	private function identity(string $email, bool $verified = true, int $companyId = 1): SsoIdentity
	{
		return new SsoIdentity(new SsoProvider(1, $companyId, 'Example', 'https://idp.example.test', 'x', '', [], true), 'subject-' . $email, $email, $verified, 'Name');
	}

	private function refused(SsoIdentity $identity): void
	{
		try
		{
			$this->service->provide($identity);
			static::fail('The login must be refused.');
		}
		catch (SsoRefusedException $e)
		{
			static::assertSame('sso_account_exists', $e->getMessage());
			static::assertFalse($this->connection->isTransactionActive(), 'rolled back');
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_sso'));
		}
	}

	public function testAnAccountWithoutPasswordIsTakenOverByTheProviderOfItsCompany(): void
	{
		$this->connection->executeStatement("UPDATE user_main SET password = '' WHERE UID = 2");

		static::assertSame(2, $this->service->provide($this->identity('anna@example.com')));
		static::assertFalse($this->connection->isTransactionActive());
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_sso WHERE UID = 2'));
	}

	/**
	 * Even without a password: the provider of another company or an email which the provider did not verify
	 * does not take over the account.
	 */
	public function testAnAccountOfAnotherCompanyOrWithoutVerifiedEmailIsRefused(): void
	{
		$this->connection->executeStatement("UPDATE user_main SET password = '' WHERE UID = 2");

		$this->refused($this->identity('anna@example.com', companyId: 2));
		$this->refused($this->identity('anna@example.com', verified: false));
	}

	public function testANewUserIsCommittedAndInTheAuditLog(): void
	{
		$UID = $this->service->provide($this->identity('carla@example.com'));

		static::assertFalse($this->connection->isTransactionActive());
		$row = $this->connection->fetchAssociative("SELECT UID, target_UID, details FROM audit_log WHERE action = 'user_created'");
		static::assertIsArray($row);
		static::assertSame([$UID, $UID, '{"username":"carla","sso":"Example"}'], [(int) $row['UID'], (int) $row['target_UID'], $row['details']]);
	}

	/**
	 * @return array<string, array{string, string}> the email and the username
	 */
	public static function usernames(): array
	{
		return [
			'small letters'             => ['Carla.Meier@example.com', 'carla.meier'],
			'only the allowed signs'    => ['carla+news@example.com', 'carlanews'],
			'nothing left'              => ['+++@example.com', 'user'],
			// 46 characters: a number for a second user still fits into the 50 of the column
			'cut'                       => [str_repeat('a', 60) . '@example.com', str_repeat('a', 46)],
		];
	}

	#[DataProvider('usernames')]
	public function testTheUsernameComesFromTheEmail(string $email, string $username): void
	{
		$UID = $this->service->provide($this->identity($email));

		static::assertSame($username, $this->connection->fetchOne('SELECT username FROM user_main WHERE UID = ?', [$UID]));
	}

	public function testAUsernameInOtherLettersCountsAsTaken(): void
	{
		$this->connection->executeStatement("UPDATE user_main SET username = 'Carla' WHERE UID = 3");

		$UID = $this->service->provide($this->identity('carla@example.com'));

		static::assertSame('carla2', $this->connection->fetchOne('SELECT username FROM user_main WHERE UID = ?', [$UID]));
	}
}
