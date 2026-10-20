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

use App\Framework\OAuth2\ScopeRepository;
use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class ScopeRepositoryTest extends TestCase
{
	private ScopeRepository $repository;

	/**
	 * setUp() wird vor jedem Test aufgerufen
	 *
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$connectionStub = $this->createStub(Connection::class);
		$this->repository = new ScopeRepository($connectionStub);
	}

	#[Group('units')]
	public function testImplementsScopeRepositoryInterface(): void
	{
		// @phpstan-ignore-next-line
		static::assertInstanceOf(ScopeRepositoryInterface::class, $this->repository);
	}

	#[Group('units')]
	public function testGetScopeEntityByIdentifierReturnsNull(): void
	{
		$result = $this->repository->getScopeEntityByIdentifier('non-existent-scope');
		static::assertNull($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFinalizeScopesReturnsEmptyArray(): void
	{
		$mockClientEntity = $this->createStub(ClientEntityInterface::class);

		$result = $this->repository->finalizeScopes([], 'authorization_code', $mockClientEntity);

		static::assertEmpty($result);
	}
}
