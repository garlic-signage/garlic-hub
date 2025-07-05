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

namespace Tests\Unit\Modules\Users\Repositories;

use App\Framework\Core\Config\Config;
use App\Modules\Users\Repositories\UserRepositoryFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserRepositoryFactoryTest extends TestCase
{
	private Config&MockObject $configMock;
	private UserRepositoryFactory $factory;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configMock = $this->createMock(Config::class);
		$connectionMock = $this->createMock(Connection::class);
		$this->factory = new UserRepositoryFactory($this->configMock, $connectionMock);
	}

	#[Group('units')]
	public function testCreateEnterpriseEdition(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);

		$result = $this->factory->create();

		static::assertArrayHasKey('contact', $result);

		static::assertArrayHasKey('stats', $result);

		static::assertArrayHasKey('vip', $result);

		static::assertArrayHasKey('security', $result);
	}

	#[Group('units')]
	public function testCreateCoreEdition(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);

		$result = $this->factory->create();

		static::assertArrayHasKey('contact', $result);
		static::assertArrayHasKey('stats', $result);
		static::assertArrayNotHasKey('vip', $result);
		static::assertArrayNotHasKey('security', $result);
	}

	#[Group('units')]
	public function testCreateEdgeEdition(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$result = $this->factory->create();

		static::assertArrayNotHasKey('contact', $result);
		static::assertArrayNotHasKey('stats', $result);
		static::assertArrayNotHasKey('vip', $result);
		static::assertArrayNotHasKey('security', $result);
	}
}
