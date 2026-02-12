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


namespace Tests\Unit\Modules\Users\Repositories\Edge;

use App\Framework\Core\Acl\EditionAclModules;
use App\Framework\Core\Config\Config;
use App\Modules\Users\Repositories\Edge\UserAclRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserAclRepositoryTest extends TestCase
{
	private Config&MockObject $ConfigMock;
	private Connection&MockObject $ConnectionMock;
	private UserAclRepository $Repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->ConfigMock = $this->createMock(Config::class);
		$this->ConnectionMock = $this->createMock(Connection::class);
		$this->Repository = new UserAclRepository($this->ConnectionMock);
	}

	#[Group('units')]
	public function testAddAdminRights(): void
	{
//		['users', 'mediapool', 'player', 'playlists'];
		$this->ConfigMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);

		$this->ConfigMock->expects($this->exactly(5))->method('getConfigValue')
			->willReturnMap([
			['moduleadmin', 'users', 'GlobalACLs', '2'],
			['moduleadmin', 'mediapool', 'GlobalACLs', '8'],
			['moduleadmin', 'player', 'GlobalACLs', '8'],
			['moduleadmin', 'playlists', 'GlobalACLs', '8'],
			['moduleadmin', 'templates', 'GlobalACLs', '8'],
		]);

		$this->ConnectionMock->expects($this->exactly(7))->method('executeStatement');
		$this->Repository->addAdminRights($this->ConfigMock, 1);
	}
}
