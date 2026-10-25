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

namespace Tests\Unit\Modules\Mediapool\Services;

use App\Framework\Database\NestedSets\Service;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Repositories\NodesRepository;
use App\Modules\Mediapool\Services\AclValidator;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Mediapool\Services\NodesService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The rights and the changes of the tree are tested with the real database in tests/Integration/Modules/Mediapool/Services
 * (NodeAccessTest, NodeManagementTest). Here is the one way out which a real database does not produce.
 */
class NodesServiceTest extends TestCase
{
	/**
	 * The folder was found, but the delete of its tree changed no row (e.g. deleted by somebody else meanwhile):
	 * the admin gets an error and not the message that everything is fine.
	 *
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testDeletingAUserFolderWhichIsGoneMeanwhileIsAnError(): void
	{
		$repository = $this->createStub(NodesRepository::class);
		$repository->method('getUserRootNode')->willReturn(['UID' => 2, 'company_id' => 1, 'node_id' => 7, 'root_id' => 7]);
		$repository->method('findAllBy')->willReturn([]);
		$repository->method('deleteBy')->willReturn(0);
		$acl = $this->createStub(AclValidator::class);
		$acl->method('isModuleAdmin')->willReturn(true);
		$session = $this->createStub(UserSession::class);
		$session->method('getUID')->willReturn(1);

		$service = new NodesService($repository, $this->createStub(Service::class), $this->createStub(MediaService::class), $acl, $session);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Delete nodes by root id failed.');

		$service->deleteUserDirectory(2);
	}
}
