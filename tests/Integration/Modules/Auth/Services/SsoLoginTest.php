<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Modules\Auth\Services;

use App\Framework\Config\Config;
use App\Framework\Security\Crypt;
use App\Modules\Auth\SsoLogin;
use App\Shared\User\Sso\SsoRefusedException;
use App\Shared\User\Sso\SsoUserProvisioning;
use Defuse\Crypto\Crypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Support\ArrayCache;
use Tests\Support\FakeOidcProvider;
use Tests\Support\MemorySession;

/**
 * The login by SSO against a provider in memory, with the real database: the user administration creates
 * or links the users. Users: 1 admin, 2 anna (anna@example.com), 3 bernd.
 */
#[Group('auth')]
class SsoLoginTest extends ContainerTestCase
{
	private const string REDIRECT = 'https://garlic-hub.test/login/sso/callback';
	private const string CLIENT_ID = 'garlic-hub';
	private const string CLIENT_SECRET = 'very-secret';

	private FakeOidcProvider $provider;
	private MemorySession $session;
	/** @var array<string,string> the settings of the section Sso */
	private array $settings;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->provider = new FakeOidcProvider(self::CLIENT_ID, self::CLIENT_SECRET);
		$this->session  = new MemorySession();
		$this->settings = [
			'enabled'         => '1',
			'name'            => 'Example',
			'issuer'          => FakeOidcProvider::ISSUER,
			'client_id'       => self::CLIENT_ID,
			'client_secret'   => Crypto::encrypt(self::CLIENT_SECRET, $this->container->get(Crypt::class)->getEncryptionKey()),
			'allowed_domains' => ''
		];
	}

	private function ssoLogin(): SsoLogin
	{
		$config = $this->createStub(Config::class);
		$config->method('isSwitchOn')->willReturnCallback(fn(string $key): bool => ($this->settings[$key] ?? '') === '1');
		$config->method('getConfigValue')->willReturnCallback(fn(string $key): string => $this->settings[$key] ?? '');

		return new SsoLogin($config, $this->session, $this->container->get(Crypt::class), $this->container->get(SsoUserProvisioning::class), $this->provider, new ArrayCache(), new NullLogger());
	}

	/**
	 * To the provider and back: the request which the browser brings back.
	 *
	 * @return array<string,string>
	 */
	private function answerOfProvider(SsoLogin $ssoLogin): array
	{
		return $this->provider->authorize($ssoLogin->authorizationUri(self::REDIRECT));
	}

	/**
	 * @param array<string,string> $query
	 */
	private function finish(SsoLogin $ssoLogin, array $query): int
	{
		return $ssoLogin->finish(new ServerRequestFactory()->createServerRequest('GET', '/login/sso/finish?' . http_build_query($query))->withQueryParams($query), self::REDIRECT);
	}

	private function login(): int
	{
		$ssoLogin = $this->ssoLogin();

		return $this->finish($ssoLogin, $this->answerOfProvider($ssoLogin));
	}

	private function assertRefused(string $key, callable $login): void
	{
		try
		{
			$login();
			static::fail('The login must be refused with ' . $key);
		}
		catch (SsoRefusedException $e)
		{
			static::assertSame($key, $e->getMessage());
		}
	}

	public function testItIsOffWithoutTheSettings(): void
	{
		static::assertTrue($this->ssoLogin()->isEnabled());
		static::assertSame('Example', $this->ssoLogin()->providerName());

		$this->settings['enabled'] = '0';
		static::assertFalse($this->ssoLogin()->isEnabled());

		$this->settings['enabled']   = '1';
		$this->settings['client_id'] = '';
		static::assertFalse($this->ssoLogin()->isEnabled());
	}

	public function testTheFirstLoginCreatesTheUserWithoutRightsAndTheNextOneFindsHim(): void
	{
		$UID = $this->login();

		$user = $this->connection->fetchAssociative('SELECT username, email, password, status FROM user_main WHERE UID = ?', [$UID]);
		static::assertSame(['username' => 'carla', 'email' => 'carla@example.com', 'password' => null, 'status' => 3], $user);
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = ?', [$UID]), 'no rights until the admin grants them');
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE UID = ? AND parent_id = 0', [$UID]), 'his media folder');
		static::assertSame('Basic ' . base64_encode(self::CLIENT_ID . ':' . self::CLIENT_SECRET), $this->provider->tokenAuthorization, 'the secret is decrypted for the provider');

		static::assertSame($UID, $this->login());
		static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	public function testTheSubjectCountsNotTheEmail(): void
	{
		$UID = $this->login();
		// the email changed at the provider: still the same user
		$this->provider->claims = ['email' => 'carla.neu@example.com'];
		static::assertSame($UID, $this->login());

		// another subject with the email of carla is not carla
		$this->provider->claims = ['sub' => 'subject-other', 'email' => 'carla@example.com', 'email_verified' => false];
		$this->assertRefused('sso_account_exists', fn() => $this->login());
	}

	public function testAnAccountWithTheSameEmailIsLinkedOnlyWhenTheProviderVerifiedIt(): void
	{
		$this->provider->claims = ['sub' => 'subject-anna', 'email' => 'Anna@Example.com', 'email_verified' => null];
		$this->assertRefused('sso_account_exists', fn() => $this->login());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_sso'));

		// Entra ID: the domain of the email is verified
		$this->provider->claims = ['sub' => 'subject-anna', 'email' => 'anna@example.com', 'email_verified' => null, 'xms_edov' => true];
		static::assertSame(2, $this->login());
	}

	public function testAUsernameWhichExistsGetsANumber(): void
	{
		$this->provider->claims = ['email' => 'anna@other.example'];

		$UID = $this->login();

		static::assertSame('anna2', $this->connection->fetchOne('SELECT username FROM user_main WHERE UID = ?', [$UID]));
	}

	public function testOnlyTheAllowedDomainsGetIn(): void
	{
		$this->settings['allowed_domains'] = 'example.org, Example.com';
		static::assertGreaterThan(3, $this->login());

		$this->settings['allowed_domains'] = 'example.org';
		$this->provider->claims = ['sub' => 'subject-dora', 'email' => 'dora@example.com'];
		$this->assertRefused('sso_domain', fn() => $this->login());
		static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	public function testAnAnswerWithAnotherStateOrASecondTimeIsRefused(): void
	{
		$ssoLogin = $this->ssoLogin();
		$answer   = $this->answerOfProvider($ssoLogin);
		$this->assertRefused('sso_expired', fn() => $this->finish($ssoLogin, ['state' => 'forged'] + $answer));

		$answer = $this->answerOfProvider($ssoLogin);
		$this->finish($ssoLogin, $answer);
		$this->assertRefused('sso_expired', fn() => $this->finish($ssoLogin, $answer));
	}

	public function testAnAnswerAfterTenMinutesIsRefused(): void
	{
		$ssoLogin = $this->ssoLogin();
		$answer   = $this->answerOfProvider($ssoLogin);
		$pending  = $this->session->data['sso_pending'];
		static::assertIsArray($pending);
		$this->session->data['sso_pending'] = ['expires' => time() - 1] + $pending;

		$this->assertRefused('sso_expired', fn() => $this->finish($ssoLogin, $answer));
	}

	public function testAnswersWithoutTheSessionOfTheBrowserAreRefused(): void
	{
		// the code was asked for in another browser: the session of this one waits for nothing
		$other  = $this->ssoLogin();
		$answer = $this->answerOfProvider($other);
		$this->session->clear();

		$this->assertRefused('sso_expired', fn() => $this->finish($this->ssoLogin(), $answer));
	}

	/**
	 * @return array<string,array{0:array<string,mixed>, 1:bool}>
	 */
	public static function forgedTokens(): array
	{
		return [
			'another nonce'    => [['nonce' => 'other'], false],
			'another audience' => [['aud' => 'other-client'], false],
			'another issuer'   => [['iss' => 'https://evil.example.test'], false],
			'expired'          => [['iat' => time() - 900, 'exp' => time() - 600], false],
			'foreign key'      => [[], true],
		];
	}

	/**
	 * @param array<string,mixed> $claims
	 */
	#[DataProvider('forgedTokens')]
	public function testAForgedTokenIsRefused(array $claims, bool $foreignKey): void
	{
		$this->provider->claims             = $claims;
		$this->provider->signWithForeignKey = $foreignKey;

		$this->assertRefused('sso_failed', fn() => $this->login());
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	public function testAWrongCodeVerifierIsRefusedByTheProvider(): void
	{
		$ssoLogin = $this->ssoLogin();
		$answer   = $this->answerOfProvider($ssoLogin);
		$pending  = $this->session->data['sso_pending'];
		static::assertIsArray($pending);
		$this->session->data['sso_pending'] = ['code_verifier' => 'stolen-code-without-verifier'] + $pending;

		$this->assertRefused('sso_failed', fn() => $this->finish($ssoLogin, $answer));
	}

	public function testTheProviderRefusingTheLoginIsNoError(): void
	{
		$ssoLogin = $this->ssoLogin();
		$state    = $this->answerOfProvider($ssoLogin)['state'];

		$this->assertRefused('sso_failed', fn() => $this->finish($ssoLogin, ['error' => 'access_denied', 'state' => $state]));
	}
}
