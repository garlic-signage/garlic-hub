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

namespace Tests\Unit\Framework\OAuth2;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\OAuth2\TokensRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TokensRepositoryTest extends TestCase
{
	private Connection&MockObject $connectionMock;
	private QueryBuilder&MockObject $queryBuilderMock;
	private TokensRepository $repository;

	/**
	 * setUp() wird vor jedem Test aufgerufen
	 *
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock   = $this->createMock(Connection::class);
		$this->repository       = new TokensRepository($this->connectionMock);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
	}

	#[Group('units')]
	public function testGetNewAuthCodeReturnsAuthCodeEntity(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$authCode = $this->repository->getNewAuthCode();
		// @phpstan-ignore-next-line
		static::assertNotNull($authCode);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPersistNewAuthCodeCallsInsert(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$mockAuthCodeEntity = $this->createStub(AuthCodeEntityInterface::class);
		$mockAuthCodeEntity->method('getIdentifier')->willReturn('test-auth-code-id');
		$mockAuthCodeEntity->method('getClient')->willReturn($this->createStub(ClientEntityInterface::class));
		$mockAuthCodeEntity->method('getUserIdentifier')->willReturn('test-user-id');
		$mockAuthCodeEntity->method('getRedirectUri')->willReturn('https://example.com/callback');
		$datetime_immutable = new DateTimeImmutable('now +1 hour');
		$mockAuthCodeEntity->method('getExpiryDateTime')->willReturn($datetime_immutable);
		$scopeEntityMock1 = $this->createStub(ScopeEntityInterface::class);
		$scopeEntityMock2 = $this->createStub(ScopeEntityInterface::class);
		$scopeEntityMock1->method('getIdentifier')->willReturn('test-scope-id1');
		$scopeEntityMock2->method('getIdentifier')->willReturn('test-scope-id2');
		$mockAuthCodeEntity->method('getScopes')->willReturn([$scopeEntityMock1, $scopeEntityMock2]);
		$this->connectionMock->expects($this->once())->method('insert')->with('oauth2_credentials',
			[
				'type'         => 'auth_code',
				'token'        => 'test-auth-code-id',
				'client_id'    => '',
				'UID'          => 'test-user-id',
				'redirect_uri' => 'https://example.com/callback',
				'scopes'       => 'test-scope-id1 test-scope-id2',
				'expires_at'   => $datetime_immutable->format('Y-m-d H:i:s'),
				'created_at'   => date('Y-m-d H:i:s'),
			]
		);
		$this->connectionMock->expects($this->once())->method('lastInsertId');
		$this->repository->persistNewAuthCode($mockAuthCodeEntity);
	}



	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testRevokeAuthCode(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('update')->with('oauth2_credentials');
		$this->queryBuilderMock->expects($this->once())->method('set')->with('revoked', ':set_revoked');

		$this->queryBuilderMock->expects($this->once())->method('executeStatement');

		$this->repository->revokeAuthCode('test-auth-code-id');
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsAuthCodeRevokedReturnsFalse(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('fetchOne')->willReturn('0');

		$result = $this->repository->isAuthCodeRevoked('test-auth-code-id');
		static::assertFalse($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsAuthCodeRevokedReturnsTrue(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('fetchOne')->willReturn(1);

		$result = $this->repository->isAuthCodeRevoked('test-auth-code-id');
		static::assertTrue($result);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testGetNewAccessTokenReturnsAccessTokenEntity(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$mockClientEntity = $this->createStub(ClientEntityInterface::class);
		$accessToken = $this->repository->getNewToken($mockClientEntity, [], 'test-user-id');
		// @phpstan-ignore-next-line
		static::assertInstanceOf(AccessTokenEntityInterface::class, $accessToken);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testGetNewAccessException(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$mockClientEntity = $this->createStub(ClientEntityInterface::class);

		static::expectException(FrameworkException::class);
		static::expectExceptionMessage('User identifier is required.');

		$accessToken = $this->repository->getNewToken($mockClientEntity, []);

	}


	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPersistNewAccessTokenCallsInsert(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$mockAccessTokenEntity = $this->createStub(AccessTokenEntityInterface::class);
		$mockAccessTokenEntity->method('getIdentifier')->willReturn('test-access-token-id');
		$mockAccessTokenEntity->method('getClient')->willReturn($this->createStub(ClientEntityInterface::class));
		$mockAccessTokenEntity->method('getUserIdentifier')->willReturn('test-user-id');
		$mockAccessTokenEntity->method('getExpiryDateTime')->willReturn(new DateTimeImmutable('now +1 hour'));
		$mockAccessTokenEntity->method('getScopes')->willReturn([]);
		$this->connectionMock->expects($this->once())->method('insert');

		$this->repository->persistNewAccessToken($mockAccessTokenEntity);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testRevokeAccessToken(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('update')->with('oauth2_credentials');
		$this->queryBuilderMock->expects($this->once())->method('set')->with('revoked', ':set_revoked');

		$this->queryBuilderMock->expects($this->once())->method('executeStatement');

		$this->repository->revokeAccessToken('test-access-token-id');
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsAccessTokenRevokedReturnsFalse(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('fetchOne')->willReturn(0);

		$result = $this->repository->isAccessTokenRevoked('test-access-token-id');
		static::assertFalse($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsAccessTokenRevokedReturnsTrue(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('fetchOne')->willReturn(1);

		$result = $this->repository->isAccessTokenRevoked('test-access-token-id');
		static::assertTrue($result);
	}

	#[Group('units')]
	public function testGetNewRefreshTokenReturnsRefreshTokenEntity(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$refreshToken = $this->repository->getNewRefreshToken();
		static::assertInstanceOf(RefreshTokenEntityInterface::class, $refreshToken);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPersistNewRefreshTokenCallsInsert(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$mockRefreshTokenEntity = $this->createStub(RefreshTokenEntityInterface::class);
		$mockRefreshTokenEntity->method('getIdentifier')->willReturn('test-refresh-token-id');
		$mockRefreshTokenEntity->method('getExpiryDateTime')->willReturn(new DateTimeImmutable('now +1 hour'));
		$mockAccessTokenEntity = $this->createStub(AccessTokenEntityInterface::class);
		$mockAccessTokenEntity->method('getClient')->willReturn($this->createStub(ClientEntityInterface::class));
		$mockAccessTokenEntity->method('getUserIdentifier')->willReturn('test-user-id');
		$mockAccessTokenEntity->method('getScopes')->willReturn([]);
		$mockRefreshTokenEntity->method('getAccessToken')->willReturn($mockAccessTokenEntity);

		$this->connectionMock->expects($this->once())->method('insert');

		$this->repository->persistNewRefreshToken($mockRefreshTokenEntity);

	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testRevokeRefreshToken(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('update')->with('oauth2_credentials');
		$this->queryBuilderMock->expects($this->once())->method('set')->with('revoked', ':set_revoked');

		$this->queryBuilderMock->expects($this->once())->method('executeStatement');

		$this->repository->revokeRefreshToken('test-refresh-token-id');
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsRefreshTokenRevokedReturnsFalse(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('fetchOne')->willReturn(0);

		$result = $this->repository->isRefreshTokenRevoked('test-refresh-token-id');
		static::assertFalse($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsRefreshTokenRevokedReturnsTrue(): void
	{
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->queryBuilderMock->expects($this->once())->method('fetchOne')->willReturn(1);

		$result = $this->repository->isRefreshTokenRevoked('test-refresh-token-id');
		static::assertTrue($result);
	}
}
