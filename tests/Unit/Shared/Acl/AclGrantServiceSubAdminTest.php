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

namespace Tests\Unit\Shared\Acl;

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclHelper;
use App\Shared\Acl\AclSections;
use App\Shared\Acl\AclUnitProvider;
use App\Shared\Audit\NullAuditLog;
use App\Shared\User\Repositories\Core\UserAclRepository;
use App\Shared\User\Repositories\Core\UserVipRepository;
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The sub admin (UID 1) of company 5 grants editor and viewer for users and units of company 5 only.
 * User 2 is in company 5, user 3 in company 6. Unit 20 belongs to company 5, unit 30 to company 6.
 */
class AclGrantServiceSubAdminTest extends TestCase
{
	private UserAclRepository $userAclRepository;
	private UserVipRepository $userVipRepository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->userAclRepository = $this->createStub(UserAclRepository::class);
		$this->userVipRepository = $this->createStub(UserVipRepository::class);
	}

	#[Group('units')]
	public function testSubAdminSetsEditorForAUserOfHisCompany(): void
	{
		$userAclRepository = $this->createMock(UserAclRepository::class);
		$userAclRepository->expects($this->once())->method('saveAcl')->with(2, 'player', 2);
		$this->userAclRepository = $userAclRepository;

		$this->service(Config::PLATFORM_EDITION_ENTERPRISE)->setRole(1, 2, 'player', AclSections::EDITOR, true);
	}

	#[Group('units')]
	public function testSubAdminMayNotGrantModuleAdmin(): void
	{
		$userAclRepository = $this->createMock(UserAclRepository::class);
		$userAclRepository->expects($this->never())->method('saveAcl');
		$this->userAclRepository = $userAclRepository;

		$this->expectException(AccessDeniedException::class);
		$this->service(Config::PLATFORM_EDITION_ENTERPRISE)->setRole(1, 2, 'player', AclSections::MODULEADMIN, true);
	}

	#[Group('units')]
	public function testSubAdminMayNotGrantForAUserOfAnotherCompany(): void
	{
		$userAclRepository = $this->createMock(UserAclRepository::class);
		$userAclRepository->expects($this->never())->method('saveAcl');
		$this->userAclRepository = $userAclRepository;

		$this->expectException(AccessDeniedException::class);
		$this->service(Config::PLATFORM_EDITION_ENTERPRISE)->setRole(1, 3, 'player', AclSections::VIEWER, true);
	}

	#[Group('units')]
	public function testSubAdminGrantsAUnitOfHisCompany(): void
	{
		$userAclRepository = $this->createStub(UserAclRepository::class);
		$userAclRepository->method('findAcl')->willReturn(2);
		$this->userAclRepository = $userAclRepository;
		$userVipRepository = $this->createMock(UserVipRepository::class);
		$userVipRepository->expects($this->once())->method('insert')->with(['UID' => 2, 'vip_name' => 'player_editor', 'unit_id' => '20']);
		$this->userVipRepository = $userVipRepository;

		$this->service(Config::PLATFORM_EDITION_ENTERPRISE)->setUnitRole(1, 2, 'player', 20, AclSections::EDITOR);
	}

	#[Group('units')]
	public function testSubAdminMayNotGrantAUnitOfAnotherCompany(): void
	{
		$userAclRepository = $this->createStub(UserAclRepository::class);
		$userAclRepository->method('findAcl')->willReturn(2);
		$this->userAclRepository = $userAclRepository;
		$userVipRepository = $this->createMock(UserVipRepository::class);
		$userVipRepository->expects($this->never())->method('insert');
		$this->userVipRepository = $userVipRepository;

		$this->expectException(AccessDeniedException::class);
		$this->service(Config::PLATFORM_EDITION_ENTERPRISE)->setUnitRole(1, 2, 'player', 30, AclSections::EDITOR);
	}

	#[Group('units')]
	public function testSubAdminGrantsNothingInCore(): void
	{
		$userAclRepository = $this->createMock(UserAclRepository::class);
		$userAclRepository->expects($this->never())->method('saveAcl');
		$this->userAclRepository = $userAclRepository;

		$this->expectException(AccessDeniedException::class);
		$this->service(Config::PLATFORM_EDITION_CORE)->setRole(1, 2, 'player', AclSections::EDITOR, true);
	}

	#[Group('units')]
	public function testTheShareDialogListsOnlyUsersOfHisCompanies(): void
	{
		$userAclRepository = $this->createStub(UserAclRepository::class);
		$this->userAclRepository = $userAclRepository;
		$userAclRepository->method('findUsersWithBit')->willReturn([
			['UID' => 1, 'username' => 'sub', 'company_id' => 5],
			['UID' => 2, 'username' => 'anna', 'company_id' => 5],
			['UID' => 3, 'username' => 'bernd', 'company_id' => 6],
		]);

		$users = $this->service(Config::PLATFORM_EDITION_ENTERPRISE)->findUsersWithRole(1, 'player', AclSections::EDITOR);

		static::assertSame([['UID' => 2, 'username' => 'anna', 'company_id' => 5]], $users);
	}

	private function service(string $edition): AclGrantService
	{
		$config = $this->createStub(Config::class);
		$config->method('getEdition')->willReturn($edition);

		$aclHelper = $this->createStub(AclHelper::class);
		$aclHelper->method('getConfig')->willReturn($config);
		$aclHelper->method('isModuleAdmin')->willReturn(false);
		$aclHelper->method('isSubAdmin')->willReturn(true);
		$aclHelper->method('hasSubAdminAccessOnCompany')->willReturnCallback(fn(int $UID, int $companyId) => $companyId === 5);
		$aclHelper->method('getAclBit')->willReturnCallback(fn(string $module, AclSections $role) => match ($role)
		{
			AclSections::VIEWER      => 1,
			AclSections::EDITOR      => 2,
			AclSections::SUBADMIN    => 4,
			AclSections::MODULEADMIN => 8,
		});
		$aclHelper->method('getVipName')->willReturnCallback(fn(string $module, AclSections $role) => $module . '_' . $role->value);

		$usersService = $this->createStub(UsersService::class);
		$usersService->method('getUserById')->willReturnCallback(
			fn(int $UID) => new UserEntity(['UID' => $UID, 'company_id' => $UID === 3 ? 6 : 5], [], [], [], [])
		);

		$units = $this->createStub(AclUnitProvider::class);
		$units->method('findUnit')->willReturnCallback(
			fn(int|string $unitId) => ['UID' => 9, 'company_id' => (int) $unitId === 20 ? 5 : 6]
		);

		return new AclGrantService(
			$aclHelper,
			$this->userAclRepository,
			$this->userVipRepository,
			$usersService,
			$this->createStub(Transactions::class),
			['player' => $units],
			new NullAuditLog()
		);
	}
}
