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

namespace Tests\Integration\Modules\Player\Services;

use App\Framework\Security\Crypt;
use App\Modules\Player\Repositories\PlayerTokenRepository;
use App\Modules\Player\Services\PlayerTokenService;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The access tokens of the players are kept encrypted in the database. The tokens are made in the order
 * of the players 3, 4, 1, so the id of a token is never the id of its player.
 */
#[Group('auth')]
class PlayerTokenTest extends ContainerTestCase
{
	private const string FUTURE = '2999-12-31 23:59:59';
	private const string PAST   = '2000-01-01 00:00:00';

	private PlayerTokenService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/players.sql');
		$this->service = new PlayerTokenService(new PlayerTokenRepository($this->connection), $this->container->get(Crypt::class));
	}

	/**
	 * @return list<array{token_id:int,player_id:int}>
	 */
	private function rows(): array
	{
		return array_map(
			static fn(array $row): array => ['token_id' => (int) $row['token_id'], 'player_id' => (int) $row['player_id']],
			$this->connection->fetchAllAssociative('SELECT token_id, player_id FROM player_tokens ORDER BY token_id')
		);
	}

	private function makeTokens(string $expiresOfPlayer1 = self::FUTURE): void
	{
		$this->service->storeToken(3, 'token-of-3', self::FUTURE);
		$this->service->storeToken(4, 'token-of-4', self::FUTURE);
		$this->service->storeToken(1, 'token-of-1', $expiresOfPlayer1);
	}

	public function testATokenIsStoredEncryptedAndReadBack(): void
	{
		$this->service->storeToken(1, 'secret-token', self::FUTURE, 'Bearer');

		$stored = $this->connection->fetchAssociative('SELECT * FROM player_tokens WHERE player_id = 1');
		static::assertIsArray($stored);
		static::assertStringNotContainsString('secret-token', (string) $stored['access_token']);
		static::assertSame('Bearer', $stored['token_type']);
		static::assertSame(['access_token' => 'secret-token', 'token_type' => 'Bearer', 'expires_at' => self::FUTURE], $this->service->getToken(1));
	}

	public function testTheTypeOfTheTokenIsKept(): void
	{
		$this->service->storeToken(1, 'secret-token', self::FUTURE, 'MAC');

		static::assertSame('MAC', $this->service->getToken(1)['token_type']);
	}

	public function testATokenForAPlayerWhichHasOneReplacesIt(): void
	{
		$this->makeTokens();
		$idBefore = $this->rows()[2]['token_id'];

		$this->service->storeToken(1, 'new-token', self::FUTURE);

		static::assertCount(3, $this->rows());
		static::assertSame($idBefore, $this->rows()[2]['token_id']);
		static::assertSame('new-token', $this->service->getToken(1)['access_token']);
		static::assertSame('token-of-3', $this->service->getToken(3)['access_token']);
	}

	public function testThePlayerWithoutATokenGetsNone(): void
	{
		static::assertSame([], $this->service->getToken(2));
		static::assertFalse($this->service->hasValidToken(2));
	}

	public function testAValidTokenIsValid(): void
	{
		$this->makeTokens();

		static::assertTrue($this->service->hasValidToken(1));
	}

	/**
	 * An expired token is deleted, and it is the token of this player. The tokens of the others stay.
	 */
	public function testAnExpiredTokenIsDeletedAndOnlyThisOne(): void
	{
		$this->makeTokens(self::PAST);

		static::assertSame([], $this->service->getToken(1));

		static::assertSame([['token_id' => 1, 'player_id' => 3], ['token_id' => 2, 'player_id' => 4]], $this->rows());
		static::assertSame('token-of-3', $this->service->getToken(3)['access_token']);
		static::assertSame('token-of-4', $this->service->getToken(4)['access_token']);
	}

	/**
	 * Some players (IAdea) do not give an expiry: the token is valid until the player says otherwise.
	 */
	public function testATokenWithoutAnExpiryStaysValid(): void
	{
		$this->service->storeToken(1, 'no-expiry', '');

		static::assertSame('no-expiry', $this->service->getToken(1)['access_token']);
		static::assertCount(1, $this->rows());
	}

	public function testATokenOfAnotherKeyIsNoToken(): void
	{
		$this->makeTokens();
		$other = new PlayerTokenService(new PlayerTokenRepository($this->connection), new Crypt(Key::createNewRandomKey()));

		static::assertSame([], $other->getToken(1));
		static::assertCount(3, $this->rows(), 'The token is not deleted: the key may come back.');
	}

	public function testATokenWhichWasChangedIsNoToken(): void
	{
		$this->makeTokens();
		$stored  = (string) $this->connection->fetchOne('SELECT access_token FROM player_tokens WHERE player_id = 1');
		$middle  = intdiv(strlen($stored), 2);
		$changed = substr($stored, 0, $middle) . ($stored[$middle] === 'a' ? 'b' : 'a') . substr($stored, $middle + 1);
		$this->connection->executeStatement('UPDATE player_tokens SET access_token = ? WHERE player_id = 1', [$changed]);

		static::assertSame([], $this->service->getToken(1));
	}

	public function testTheExpiredTokensAreCleanedUp(): void
	{
		$this->makeTokens(self::PAST);
		$this->service->storeToken(2, 'token-of-2', self::PAST);

		static::assertSame(2, $this->service->cleanupExpiredTokens());

		static::assertSame([['token_id' => 1, 'player_id' => 3], ['token_id' => 2, 'player_id' => 4]], $this->rows());
	}

	public function testNothingIsCleanedUpWhenNothingIsExpired(): void
	{
		$this->makeTokens();

		static::assertSame(0, $this->service->cleanupExpiredTokens());
		static::assertCount(3, $this->rows());
	}

	public function testATokenIsRefreshed(): void
	{
		$this->makeTokens();

		$this->service->refreshToken(1, ['access_token' => 'fresh', 'expires_at' => '2998-01-01 00:00:00', 'token_type' => 'Bearer']);

		static::assertSame(['access_token' => 'fresh', 'token_type' => 'Bearer', 'expires_at' => '2998-01-01 00:00:00'], $this->service->getToken(1));
		static::assertCount(3, $this->rows());
	}
}
