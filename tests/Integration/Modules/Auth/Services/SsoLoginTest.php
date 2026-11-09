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

use App\Framework\Security\Crypt;
use App\Modules\Auth\SsoLogin;
use App\Shared\User\UserStatus;
use App\Shared\User\Repositories\Core\SsoProvidersRepository;
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
 * or links the users. Users: 1 admin, 2 anna (anna@example.com), 3 bernd. The provider is the one with id 1.
 */
#[Group('auth')]
class SsoLoginTest extends ContainerTestCase
{
	private const string REDIRECT = 'https://garlic-hub.test/login/sso/callback';
	private const string CLIENT_ID = 'garlic-hub';
	private const string CLIENT_SECRET = 'very-secret';

	private FakeOidcProvider $provider;
	private MemorySession $session;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->provider = new FakeOidcProvider(self::CLIENT_ID, self::CLIENT_SECRET);
		$this->session  = new MemorySession();
		$this->connection->insert('sso_providers', [
			'provider_id'   => 1,
			'name'          => 'Example',
			'issuer'        => FakeOidcProvider::ISSUER,
			'client_id'     => self::CLIENT_ID,
			'client_secret' => Crypto::encrypt(self::CLIENT_SECRET, $this->container->get(Crypt::class)->getEncryptionKey())
		]);
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	private function changeProvider(array $fields): void
	{
		$this->connection->update('sso_providers', $fields, ['provider_id' => 1]);
	}

	private function ssoLogin(): SsoLogin
	{
		return new SsoLogin(new SsoProvidersRepository($this->connection), $this->session, $this->container->get(Crypt::class), $this->container->get(SsoUserProvisioning::class), $this->provider, new ArrayCache(), new NullLogger());
	}

	/**
	 * To the provider and back: the request which the browser brings back.
	 *
	 * @return array<string,string>
	 */
	private function answerOfProvider(SsoLogin $ssoLogin): array
	{
		$uri = $ssoLogin->authorizationUri(1, self::REDIRECT);
		static::assertNotNull($uri);

		return $this->provider->authorize($uri);
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

	public function testOnlyTheEnabledProvidersAreOffered(): void
	{
		$this->connection->insert('sso_providers', ['provider_id' => 2, 'name' => 'Another', 'issuer' => 'https://other.example.test', 'client_id' => 'x', 'client_secret' => '']);
		static::assertSame(['Another', 'Example'], array_map(static fn($provider) => $provider->name, $this->ssoLogin()->providers()));

		$this->changeProvider(['enabled' => 0]);
		static::assertSame(['Another'], array_map(static fn($provider) => $provider->name, $this->ssoLogin()->providers()));
		static::assertNull($this->ssoLogin()->authorizationUri(1, self::REDIRECT));
		static::assertNull($this->ssoLogin()->authorizationUri(99, self::REDIRECT));
	}

	public function testAProviderTurnedOffMeanwhileLogsNobodyIn(): void
	{
		$ssoLogin = $this->ssoLogin();
		$answer   = $this->answerOfProvider($ssoLogin);
		$this->changeProvider(['enabled' => 0]);

		$this->assertRefused('sso_failed', fn() => $this->finish($ssoLogin, $answer));
	}

	/**
	 * The same Google account at the provider of another company (Enterprise) does not log into the user of the first one.
	 */
	public function testTheIdentityBelongsToTheProviderNotToTheIssuer(): void
	{
		$UID = $this->login();
		$this->connection->insert('sso_providers', ['provider_id' => 2, 'company_id' => 2, 'name' => 'Other company', 'issuer' => FakeOidcProvider::ISSUER, 'client_id' => self::CLIENT_ID,
			'client_secret' => Crypto::encrypt(self::CLIENT_SECRET, $this->container->get(Crypt::class)->getEncryptionKey())]);

		$ssoLogin = $this->ssoLogin();
		$uri      = $ssoLogin->authorizationUri(2, self::REDIRECT);
		static::assertNotNull($uri);
		$answer = $this->provider->authorize($uri);

		$this->assertRefused('sso_account_exists', fn() => $this->finish($ssoLogin, $answer));
		static::assertSame([['UID' => $UID, 'provider_id' => 1]], $this->connection->fetchAllAssociative('SELECT UID, provider_id FROM user_sso'));
	}

	public function testANewUserGetsTheCompanyOfTheProviderAndAnAccountOfAnotherCompanyIsNotLinked(): void
	{
		$this->changeProvider(['company_id' => 2]);
		$UID = $this->login();
		static::assertSame(2, (int) $this->connection->fetchOne('SELECT company_id FROM user_main WHERE UID = ?', [$UID]));

		// anna is of company 1
		$this->provider->claims = ['sub' => 'subject-anna', 'email' => 'anna@example.com', 'email_verified' => true];
		$this->assertRefused('sso_account_exists', fn() => $this->login());
	}

	public function testTheFirstLoginCreatesTheUserWithoutRightsAndTheNextOneFindsHim(): void
	{
		$UID = $this->login();

		$user = $this->connection->fetchAssociative('SELECT username, email, password, status FROM user_main WHERE UID = ?', [$UID]);
		static::assertSame(['username' => 'carla', 'email' => 'carla@example.com', 'password' => null, 'status' => UserStatus::REGISTERED->value], $user, 'active at once: the provider verified the email, a status NOT_VERIFIED would wait for a password');
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
		$this->assertRefused('sso_email_not_verified', fn() => $this->login());
	}

	public function testAnAccountWithTheSameEmailIsLinkedOnlyWhenTheProviderVerifiedIt(): void
	{
		$this->provider->claims = ['sub' => 'subject-anna', 'email' => 'Anna@Example.com', 'email_verified' => null];
		$this->assertRefused('sso_email_not_verified', fn() => $this->login());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_sso'));

		// Entra ID: the domain of the email is verified; anna was switched to SSO, she has no password
		$this->connection->executeStatement("UPDATE user_main SET password = '' WHERE UID = 2");
		$this->provider->claims = ['sub' => 'subject-anna', 'email' => 'anna@example.com', 'email_verified' => null, 'xms_edov' => true];
		static::assertSame(2, $this->login());
	}

	/**
	 * Without a verified email nobody gets in: no new account (it would pass the allowed domains with any email) and
	 * no linked one. Entra ID needs the optional claim xms_edov for it.
	 */
	public function testALoginWithoutAVerifiedEmailIsRefused(): void
	{
		$this->changeProvider(['allowed_domains' => 'example.com']);
		foreach ([false, null, 'true'] as $verified)
		{
			$this->provider->claims = ['email_verified' => $verified];
			$this->assertRefused('sso_email_not_verified', fn() => $this->login());
		}
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));

		// a user who logged in before is refused as well when the provider no longer verifies his email
		$this->provider->claims = [];
		$UID = $this->login();
		$this->provider->claims = ['email_verified' => false];
		$this->assertRefused('sso_email_not_verified', fn() => $this->login());
		$this->provider->claims = [];
		static::assertSame($UID, $this->login());
	}

	/**
	 * Google verifies the email of a private account, too. With allowed domains only the Workspace of the company
	 * gets in: the claim hd.
	 */
	public function testWithGoogleOnlyTheWorkspaceOfTheAllowedDomainsGetsIn(): void
	{
		$this->provider = new FakeOidcProvider(self::CLIENT_ID, self::CLIENT_SECRET, 'https://accounts.google.com');
		$this->changeProvider(['issuer' => 'https://accounts.google.com', 'allowed_domains' => 'example.com']);

		foreach ([null, 'other.example', ['example.com']] as $hd)
		{
			$this->provider->claims = ['hd' => $hd];
			$this->assertRefused('sso_domain', fn() => $this->login());
		}
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));

		$this->provider->claims = ['hd' => 'Example.com'];
		static::assertGreaterThan(3, $this->login());

		// without allowed domains the admin lets in every Google account
		$this->changeProvider(['allowed_domains' => '']);
		$this->provider->claims = ['sub' => 'subject-private', 'email' => 'private@gmail.example'];
		static::assertGreaterThan(4, $this->login());
	}

	/**
	 * Whoever has the email of an admin at the provider must not get his account: an account with a password logs in
	 * by it. The admin switches a user to SSO, which removes the password.
	 */
	public function testAnAccountWithAPasswordIsNotTakenOver(): void
	{
		$this->provider->claims = ['sub' => 'subject-admin', 'email' => 'admin@example.com'];
		$this->assertRefused('sso_account_exists', fn() => $this->login());

		$this->connection->executeStatement('UPDATE user_main SET password = NULL WHERE UID = 1');
		static::assertSame(1, $this->login());
	}

	/**
	 * The email of a former employee is given to the next one, who gets another subject at the provider: he does
	 * not get the account of the former one.
	 */
	public function testAnAccountWithAnIdentityAtTheProviderIsNotTakenOverByAnother(): void
	{
		$UID = $this->login();
		static::assertNull($this->connection->fetchOne('SELECT password FROM user_main WHERE UID = ?', [$UID]));

		$this->provider->claims = ['sub' => 'subject-successor', 'email' => 'carla@example.com'];
		$this->assertRefused('sso_account_exists', fn() => $this->login());
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_sso WHERE UID = ?', [$UID]));
	}

	public function testAUsernameWhichExistsGetsANumber(): void
	{
		$this->provider->claims = ['email' => 'anna@other.example'];

		$UID = $this->login();

		static::assertSame('anna2', $this->connection->fetchOne('SELECT username FROM user_main WHERE UID = ?', [$UID]));
	}

	public function testOnlyTheAllowedDomainsGetIn(): void
	{
		$this->changeProvider(['allowed_domains' => 'example.org, Example.com']);
		static::assertGreaterThan(3, $this->login());

		$this->changeProvider(['allowed_domains' => 'example.org']);
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

	/**
	 * An ID token in the address instead of the code: without the code exchange the secret and PKCE would not count.
	 */
	public function testAnIdTokenInTheAddressIsNoLogin(): void
	{
		$ssoLogin = $this->ssoLogin();
		$answer   = $this->answerOfProvider($ssoLogin);
		$idToken  = $this->provider->idTokenFor($answer['code']);

		$this->assertRefused('sso_failed', fn() => $this->finish($ssoLogin, ['state' => $answer['state'], 'id_token' => $idToken]));
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
