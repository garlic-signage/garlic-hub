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

namespace Tests\Integration\Db\Modules\Player\Repositories;

use App\Modules\Player\Repositories\PlayerTokenRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Db\RepositoryTestCase;

#[Group('db')]
class PlayerTokenRepositoryTest extends RepositoryTestCase
{
	private PlayerTokenRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new PlayerTokenRepository($this->connection);
		$this->loadFixture('player/playlists.sql');
		$this->loadFixture('player/players.sql');
		$this->loadFixture('player/player_tokens.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindByPlayerId(): void
	{
		$token = $this->repository->findByPlayerId(1);

		static::assertSame('valid-token', $token['access_token']);
		static::assertSame('Bearer', $token['token_type']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindByPlayerIdWithoutTokenReturnsEmptyArray(): void
	{
		static::assertSame([], $this->repository->findByPlayerId(3));
	}

	/**
	 * @throws Exception
	 */
	public function testFindValidTokens(): void
	{
		$tokens = $this->repository->findValidTokens();

		static::assertSame(['valid-token'], array_column($tokens, 'access_token'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindExpiredTokens(): void
	{
		$tokens = $this->repository->findExpiredTokens();

		static::assertSame(['expired-token'], array_column($tokens, 'access_token'));
	}

	/**
	 * @throws Exception
	 */
	public function testUpdateForPlayerSetsUpdatedAtOfThatPlayerOnly(): void
	{
		$affected = $this->repository->updateForPlayer(1, []);

		static::assertSame(1, $affected);
		static::assertNotSame('2025-01-01 00:00:00', $this->connection->fetchOne('SELECT updated_at FROM player_tokens WHERE player_id = 1'));
		static::assertSame('2025-01-01 00:00:00', $this->connection->fetchOne('SELECT updated_at FROM player_tokens WHERE player_id = 2'));
	}

	/**
	 * @throws Exception
	 */
	public function testOnlyOneTokenPerPlayerAllowed(): void
	{
		$this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

		$this->repository->insert(['player_id' => 1, 'access_token' => 'second', 'expires_at' => '2999-12-31 23:59:59']);
	}
}
