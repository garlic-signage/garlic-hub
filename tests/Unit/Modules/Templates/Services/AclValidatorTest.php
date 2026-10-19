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

namespace Tests\Unit\Modules\Templates\Services;

use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Templates\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AclValidatorTest extends TestCase
{
	private AclHelper&MockObject $aclHelperMock;
	private Config&Stub $configStub;
	private AclValidator $aclValidator;

	/** @var array{UID: int, company_id: int, template_id: int} */
	private array $template = ['UID' => 2, 'company_id' => 4, 'template_id' => 12];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->aclHelperMock = $this->createMock(AclHelper::class);
		$this->configStub    = $this->createStub(Config::class);

		$this->aclValidator  = new AclValidator($this->aclHelperMock);
	}

	#[Group('units')]
	public function testModuleName(): void
	{
		$this->aclHelperMock->expects($this->never())->method('isModuleAdmin');

		static::assertSame('templates', $this->aclValidator->getModuleName());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCanCreateAsModuleAdmin(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with(1, 'templates')->willReturn(true);
		$this->aclHelperMock->expects($this->never())->method('isSubAdmin');

		static::assertTrue($this->aclValidator->canCreate(1));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCanCreateAsSubAdmin(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->with(1, 'templates')->willReturn(true);

		static::assertTrue($this->aclValidator->canCreate(1));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCanCreateAsUser(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->willReturn(false);

		static::assertFalse($this->aclValidator->canCreate(1));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateAsModuleAdmin(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with(1, 'templates')->willReturn(true);
		$this->aclHelperMock->expects($this->never())->method('getConfig');

		static::assertTrue($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateInEdgeEdition(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->mockEdition(Config::PLATFORM_EDITION_EDGE);
		$this->aclHelperMock->expects($this->never())->method('hasSubAdminAccessOnCompany');

		static::assertFalse($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateAsSubAdminWithCompanyAccess(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->mockEdition(Config::PLATFORM_EDITION_ENTERPRISE);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->with(1, 'templates')->willReturn(true);
		$this->aclHelperMock->expects($this->once())->method('hasSubAdminAccessOnCompany')
			->with(1, 4, 'templates')
			->willReturn(true);

		static::assertTrue($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateAsSubAdminWithoutCompanyAccess(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->mockEdition(Config::PLATFORM_EDITION_ENTERPRISE);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->willReturn(true);
		$this->aclHelperMock->expects($this->once())->method('hasSubAdminAccessOnCompany')->willReturn(false);

		static::assertFalse($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsTemplateEditableAsAdmin(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(true);
		$this->aclHelperMock->expects($this->never())->method('isEditor');

		static::assertTrue($this->aclValidator->isTemplateEditable(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsTemplateEditableAsEditorWithAccess(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->mockEdition(Config::PLATFORM_EDITION_EDGE);
		$this->aclHelperMock->expects($this->once())->method('isEditor')->with(1, 'templates')->willReturn(true);
		$this->aclHelperMock->expects($this->once())->method('hasEditorAccessOnUnit')
			->with(1, 12, 'templates')
			->willReturn(true);

		static::assertTrue($this->aclValidator->isTemplateEditable(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsTemplateEditableWithoutRights(): void
	{
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->mockEdition(Config::PLATFORM_EDITION_EDGE);
		$this->aclHelperMock->expects($this->once())->method('isEditor')->willReturn(false);
		$this->aclHelperMock->expects($this->never())->method('hasEditorAccessOnUnit');

		static::assertFalse($this->aclValidator->isTemplateEditable(1, $this->template));
	}

	private function mockEdition(string $edition): void
	{
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($this->configStub);
		$this->configStub->method('getEdition')->willReturn($edition);
	}
}
