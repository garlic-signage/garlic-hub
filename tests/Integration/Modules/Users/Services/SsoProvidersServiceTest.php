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
use App\Framework\Security\Crypt;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\SsoProvidersService;
use App\Shared\Audit\AuditLogRepository;
use App\Shared\Audit\DatabaseAuditLog;
use App\Shared\User\Repositories\Core\SsoProvidersRepository;
use App\Shared\User\Repositories\Core\UserSsoRepository;
use App\Shared\User\UsersService;
use Defuse\Crypto\Crypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What the service of the SSO providers stores and refuses, with the audit log on.
 * Users: 1 admin (module admin of users), 2 anna, 3 bernd.
 */
#[Group('acl')]
class SsoProvidersServiceTest extends ContainerTestCase
{
	private SsoProvidersService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->service = new SsoProvidersService(new SsoProvidersRepository($this->connection), new UserSsoRepository($this->connection), $this->container->get(AclValidator::class),
			$this->container->get(UsersService::class), $this->container->get(Crypt::class), $this->container->get(Transactions::class), new DatabaseAuditLog($this->container->get(AuditLogRepository::class)));
	}

	/**
	 * @param array<string,string|bool> $changes
	 * @return list<string>
	 */
	private function save(array $changes = [], int $providerId = 0): array
	{
		$input = array_merge(['name' => 'Google', 'issuer' => 'https://accounts.google.com', 'client_id' => 'the-client', 'client_secret' => 'very-secret', 'allowed_domains' => '', 'enabled' => true], $changes);

		/** @var array{name:string, issuer:string, client_id:string, client_secret:string, allowed_domains:string, enabled:bool} $input */
		return $this->service->save(1, $providerId === 0 ? null : $this->service->find(1, $providerId), $input);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function stored(): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM sso_providers');
		static::assertIsArray($row);

		return $row;
	}

	public function testTheInputIsStoredWithoutTheSpacesAround(): void
	{
		static::assertSame([], $this->save(['name' => ' Google ', 'issuer' => ' https://accounts.google.com/ ', 'client_id' => ' the-client ', 'client_secret' => ' very-secret ']));

		$row = $this->stored();
		static::assertSame(['Google', 'https://accounts.google.com', 'the-client'], [$row['name'], $row['issuer'], $row['client_id']]);
		static::assertSame('very-secret', Crypto::decrypt((string) $row['client_secret'], $this->container->get(Crypt::class)->getEncryptionKey()));
	}

	public function testOnlySpacesAreNoInput(): void
	{
		static::assertSame(['sso_name_invalid', 'sso_client_id_invalid', 'sso_client_secret_missing'], $this->save(['name' => '  ', 'client_id' => '  ', 'client_secret' => '  ']));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));
	}

	public function testTheProviderBelongsToTheCompanyOfTheAdmin(): void
	{
		$this->connection->executeStatement('UPDATE user_main SET company_id = 2 WHERE UID = 1');

		$this->save();

		static::assertSame(2, (int) $this->stored()['company_id']);
	}

	/**
	 * @return array<string, array{array<string,string>, list<string>}> the input and the errors
	 */
	public static function lengths(): array
	{
		$issuer = 'https://idp.example.com/';
		return [
			'name of 100 characters'   => [['name' => str_repeat('ä', 100)], []],
			'name of 101 characters'   => [['name' => str_repeat('ä', 101)], ['sso_name_invalid']],
			'issuer of 255 characters' => [['issuer' => $issuer . str_repeat('a', 255 - strlen($issuer))], []],
			'issuer of 256 characters' => [['issuer' => $issuer . str_repeat('a', 256 - strlen($issuer))], ['sso_issuer_invalid']],
			'client id of 255'         => [['client_id' => str_repeat('c', 255)], []],
			'client id of 256'         => [['client_id' => str_repeat('c', 256)], ['sso_client_id_invalid']],
		];
	}

	/**
	 * @param array<string,string> $changes
	 * @param list<string> $errors
	 */
	#[DataProvider('lengths')]
	public function testTheLengthsAreCheckedUpToTheLimit(array $changes, array $errors): void
	{
		static::assertSame($errors, $this->save($changes));
	}

	/**
	 * A domain without a dot is no domain of a company; several wrong domains are one error.
	 */
	public function testWrongDomainsAreOneError(): void
	{
		static::assertSame(['sso_domains_invalid'], $this->save(['allowed_domains' => 'localhost']));
		static::assertSame(['sso_domains_invalid'], $this->save(['allowed_domains' => 'localhost, intranet']));
	}

	public function testTheAuditLogTellsWhetherTheSecretChanged(): void
	{
		$this->save();
		$providerId = (int) $this->stored()['provider_id'];
		$this->save(['client_secret' => ''], $providerId);

		static::assertSame([
			'{"name":"Google","issuer":"https:\/\/accounts.google.com","client_id":"the-client","allowed_domains":"","enabled":1,"secret_changed":true}',
			'{"name":"Google","issuer":"https:\/\/accounts.google.com","client_id":"the-client","allowed_domains":"","enabled":1,"secret_changed":false}',
		], $this->connection->fetchFirstColumn("SELECT details FROM audit_log WHERE action = 'sso_provider_saved' ORDER BY id"));
	}

	public function testTheDeletionIsCommittedAndInTheAuditLog(): void
	{
		$this->save();
		$provider = $this->service->find(1, (int) $this->stored()['provider_id']);
		static::assertNotNull($provider);

		$this->service->delete(1, $provider);

		static::assertFalse($this->connection->isTransactionActive());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));
		static::assertSame('{"name":"Google"}', $this->connection->fetchOne("SELECT details FROM audit_log WHERE action = 'sso_provider_deleted'"));
	}
}
