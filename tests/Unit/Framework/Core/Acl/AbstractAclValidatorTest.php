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

use App\Framework\Core\Acl\AbstractAclValidator;
use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConcreteAclValidator extends AbstractAclValidator
{

}

class AbstractAclValidatorTest extends TestCase
{
	private ConcreteAclValidator $aclValidator;
	private AclHelper&MockObject $aclHelperMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->aclHelperMock = $this->createMock(AclHelper::class);
		$this->aclValidator = new ConcreteAclValidator('testModule', $this->aclHelperMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetConfig(): void
	{
		$configMock = $this->createMock(Config::class);
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($configMock);
		static::assertSame($configMock, $this->aclValidator->getConfig());
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsSimpleAdminReturnsTrueWhenIsModuleAdmin(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with(1, 'testModule')->willReturn(true);
		$this->aclHelperMock->expects($this->never())->method('isSubAdmin');

		static::assertTrue($this->aclValidator->isSimpleAdmin(1));
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsSimpleAdminReturnsTrueWhenIsSubAdmin(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with(1, 'testModule')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->with(1, 'testModule')->willReturn(true);

		static::assertTrue($this->aclValidator->isSimpleAdmin(1));
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsSimpleAdminReturnsFalse(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with(1, 'testModule')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->with(1, 'testModule')->willReturn(false);

		static::assertFalse($this->aclValidator->isSimpleAdmin(1));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsAdminSubAdminAccess(): void
	{
		$UID = 1;
		$unit = ['UID' => 1, 'company_id' => 4];

		$configMock = $this->createMock(Config::class);
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($configMock);
		$configMock->expects($this->once())->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);

		$this->aclHelperMock->method('isModuleAdmin')
			->with($UID)
			->willReturn(false);

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID)
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, 4)
			->willReturn(true);


		$result = $this->aclValidator->isAdmin($UID, $unit);

		static::assertTrue($result);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsAdminSubAdminAccessFailsOfNotExistingParams(): void
	{
		$UID = 1;
		$unit = ['UID' => 1];

		$configMock = $this->createMock(Config::class);
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($configMock);
		$configMock->expects($this->once())->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);

		$this->aclHelperMock->method('isModuleAdmin')
			->with($UID)
			->willReturn(false);

		$this->aclHelperMock->expects($this->never())->method('isSubAdmin');

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Missing company id or UID in unit data.');

		$result = $this->aclValidator->isAdmin($UID, $unit);

		static::assertTrue($result);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsAdminFails(): void
	{
		$UID = 1;
		$unit = ['UID' => 2, 'company_id' => 4];
		$result = $this->aclValidator->isAdmin($UID, $unit);

		static::assertFalse($result);
	}

}
