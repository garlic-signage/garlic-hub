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

use App\Framework\Core\Acl\AclHelper;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AclValidatorTest extends TestCase
{
	private AclValidator $aclValidator;
	private AclHelper&MockObject $aclHelperMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->aclHelperMock = $this->createMock(AclHelper::class);

		$this->aclValidator    = new AclValidator($this->aclHelperMock);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsFailsNoUID(): void
	{
		$this->aclHelperMock->expects($this->never())->method($this->anything());
		$UID       = 1;
		$directory = ['foo' => 'bar'];
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Missing important values in media directory data struct.');

		$this->aclValidator->checkDirectoryPermissions($UID, $directory);

	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsOwnerIsSame(): void
	{
		$UID = 1;
		$directory = ['UID' => $UID, 'company_id' => 1, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 0];

		// the owner gets full rights, no further role checks
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with($UID, 'mediapool')->willReturn(false);
		$this->aclHelperMock->expects($this->never())->method('isSubAdmin');
		$this->aclHelperMock->expects($this->never())->method('isEditor');

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'], $permissions);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsModuleAdmin(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 1, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 0];

		$this->aclHelperMock->method('isModuleAdmin')
			->with($UID, 'mediapool')
			->willReturn(true);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'], $permissions);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsSubAdminWrongAccess(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 1, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 0];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'mediapool')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $directory['company_id'], 'mediapool')
			->willReturn(false);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => false, 'read' => false, 'edit' => false, 'share' => ''], $permissions);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsSubAdminSucceedRootDir(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 4, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 0];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'mediapool')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $directory['company_id'], 'mediapool')
			->willReturn(true);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => true, 'read' => true, 'edit' => false, 'share' => 'company'], $permissions);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsEditorWrongAccess(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 4, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'mediapool')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $directory['node_id'], 'mediapool')
			->willReturn(false);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => false, 'read' => false, 'edit' => false, 'share' => ''], $permissions);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsSubAdminSucceedNormalDir(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 4, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 12];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'mediapool')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $directory['company_id'], 'mediapool')
			->willReturn(true);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => true, 'read' => true, 'edit' => true, 'share' => 'company'], $permissions);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsEditorSucceed(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 4, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'mediapool')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $directory['node_id'], 'mediapool')
			->willReturn(true);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => true, 'read' => true, 'edit' => false, 'share' => ''], $permissions);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsViewerWrongNode(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 4, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 12];

		$this->aclHelperMock->method('isViewer')
			->with($UID, 'mediapool')
			->willReturn(true);

		$this->aclHelperMock->method('hasViewerAccessOnUnit')
			->with($UID, $directory['node_id'], 'mediapool')
			->willReturn(false);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => false, 'read' => false, 'edit' => false, 'share' => ''], $permissions);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsViewerSucceed(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 4, 'node_id' => 1, 'visibility' => 0, 'parent_id' => 12];

		$this->aclHelperMock->method('isViewer')
			->with($UID, 'mediapool')
			->willReturn(true);

		$this->aclHelperMock->method('hasViewerAccessOnUnit')
			->with($UID, $directory['node_id'], 'mediapool')
			->willReturn(true);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => false, 'read' => true, 'edit' => false, 'share' => ''], $permissions);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckDirectoryPermissionsIsPublic(): void
	{
		$UID = 1;
		$directory = ['UID' => 2, 'company_id' => 4, 'node_id' => 1, 'visibility' => AclValidator::VISIBILITY_PUBLIC, 'parent_id' => 0];

		// no role at all, only the public visibility grants reading
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with($UID, 'mediapool')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->with($UID, 'mediapool')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isEditor')->with($UID, 'mediapool')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isViewer')->with($UID, 'mediapool')->willReturn(false);

		$permissions = $this->aclValidator->checkDirectoryPermissions($UID, $directory);

		static::assertEquals(['create' => false, 'read' => true, 'edit' => false, 'share' => ''], $permissions);
	}
}
