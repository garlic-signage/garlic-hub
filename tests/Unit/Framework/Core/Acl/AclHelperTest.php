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

namespace Tests\Unit\Framework\Core\Acl;

use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Modules\Profile\Entities\UserEntity;
use App\Modules\Users\Services\UsersService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AclHelperTest extends TestCase
{
	private AclHelper $aclHelper;
	private Config&Stub $configStub;
	private UsersService&Stub $usersServiceStub;
	private UserEntity&Stub $userEntityStub;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub       = $this->createStub(Config::class);
		$this->userEntityStub   = $this->createStub(UserEntity::class);
		$this->usersServiceStub = $this->createStub(UsersService::class);
		$this->aclHelper        = new AclHelper($this->usersServiceStub, $this->configStub);
	}

	#[Group('units')]
	public function testGetConfig(): void
	{
		static::assertSame($this->configStub, $this->aclHelper->getConfig());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsModuleAdminReturnsTrueForValidAdmin(): void
	{
		$UID = 1;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 8]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isModuleAdmin($UID, $moduleName);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsModuleAdminReturnsFalseForInvalidAdmin(): void
	{
		$UID = 2;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 4]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isModuleAdmin($UID, $moduleName);

		static::assertFalse($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsSubAdminReturnsTrueForValidSubAdmin(): void
	{
		$UID = 1;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 4]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isSubAdmin($UID, $moduleName);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsSubAdminReturnsFalseForInvalidSubAdmin(): void
	{
		$UID = 2;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 2]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isSubAdmin($UID, $moduleName);

		static::assertFalse($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsEditorReturnsTrueForValidEditor(): void
	{
		$UID = 1;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 2]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isEditor($UID, $moduleName);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsEditorReturnsFalseForInvalidEditor(): void
	{
		$UID = 1;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 1]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isEditor($UID, $moduleName);

		static::assertFalse($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsViewerReturnsTrueForValidViewer(): void
	{
		$UID = 1;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 1]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isViewer($UID, $moduleName);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsViewerReturnsFalseForInvalidViewer(): void
	{
		$UID = 1;
		$moduleName = 'testModule';
		$this->mockConfigValues();

		$this->userEntityStub->method('getAcl')->willReturn([
			['module' => $moduleName, 'acl' => 0]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->isViewer($UID, $moduleName);

		static::assertFalse($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasSubAdminAccessOnCompanyReturnsTrueForValidAccess(): void
	{
		$UID = 1;
		$companyId = 100;
		$moduleName = 'testModule_subadmin';

		$this->userEntityStub->method('getVip')->willReturn([
			[$moduleName => $companyId]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasSubAdminAccessOnCompany($UID, $companyId, 'testModule');

		static::assertTrue($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasSubAdminAccessOnCompanyReturnsFalseForInvalidAccess(): void
	{
		$UID = 1;
		$companyId = 100;
		$moduleName = 'testModule_subadmin';

		$this->userEntityStub->method('getVip')->willReturn([
			[$moduleName => 101]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasSubAdminAccessOnCompany($UID, $companyId, 'testModule');

		static::assertFalse($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasSubAdminAccessOnCompanyReturnsFalseWhenVIPIsEmpty(): void
	{
		$UID = 1;
		$companyId = 100;
		$this->userEntityStub->method('getVip')->willReturn([]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasSubAdminAccessOnCompany($UID, $companyId, 'testModule');
		static::assertFalse($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasEditorAccessOnUnitReturnsTrueForValidAccess(): void
	{
		$UID = 1;
		$unitId = 200;
		$moduleName = 'testModule_editor';

		$this->userEntityStub->method('getVip')->willReturn([
			[$moduleName => $unitId]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasEditorAccessOnUnit($UID, $unitId, 'testModule');

		static::assertTrue($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasViewerAccessOnUnitReturnsTrueForValidAccess(): void
	{
		$UID = 1;
		$unitId = 300;
		$moduleName = 'testModule_viewer';

		$this->userEntityStub->method('getVip')->willReturn([
			[$moduleName => $unitId]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasViewerAccessOnUnit($UID, $unitId, 'testModule');

		static::assertTrue($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasViewerAccessOnUnitReturnsFalseForInvalidAccess(): void
	{
		$UID = 1;
		$unitId = 300;
		$moduleName = 'testModule_viewer';

		$this->userEntityStub->method('getVip')->willReturn([
			[$moduleName => 301]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasViewerAccessOnUnit($UID, $unitId, 'testModule');

		static::assertFalse($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasViewerAccessOnUnitReturnsFalseWhenVIPIsEmpty(): void
	{
		$UID = 1;
		$unitId = 300;

		$this->userEntityStub->method('getVip')->willReturn([]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasViewerAccessOnUnit($UID, $unitId, 'testModule');

		static::assertFalse($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasEditorAccessOnUnitReturnsFalseForInvalidAccess(): void
	{
		$UID = 1;
		$unitId = 200;
		$moduleName = 'testModule_editor';

		$this->userEntityStub->method('getVip')->willReturn([
			[$moduleName => 201]
		]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasEditorAccessOnUnit($UID, $unitId, 'testModule');

		static::assertFalse($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHasEditorAccessOnUnitReturnsFalseWhenVIPIsEmpty(): void
	{
		$UID = 1;
		$unitId = 200;

		$this->userEntityStub->method('getVip')->willReturn([]);

		$this->usersServiceStub->method('getUserById')->willReturn($this->userEntityStub);

		$result = $this->aclHelper->hasEditorAccessOnUnit($UID, $unitId, 'testModule');
		static::assertFalse($result);
	}

	private function mockConfigValues(): void
	{
		$this->configStub->method('getConfigValue')
			->willReturnCallback(function ($key)
			{
				return match ($key)
				{
					'moduleadmin' => '8',
					'subadmin' => '4',
					'editor' => '2',
					'viewer' => '1',
					default => '0',
				};
			});
	}

}
