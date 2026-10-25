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

use App\Shared\Acl\AclHelper;
use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Templates\Services\AclValidator;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AclValidatorTest extends TestCase
{
	private AclHelper&Stub $aclHelperStub;
	private AclValidator $aclValidator;

	/** @var array{UID: int, company_id: int, template_id: int} */
	private array $template = ['UID' => 2, 'company_id' => 4, 'template_id' => 12];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->aclHelperStub = $this->createStub(AclHelper::class);
		$this->aclValidator  = new AclValidator($this->aclHelperStub);
	}

	#[Group('units')]
	public function testModuleName(): void
	{
		static::assertSame('templates', $this->aclValidator->getModuleName());
	}

	#[Group('units')]
	public function testCanCreateAsModuleAdmin(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturnMap([[1, 'templates', true]]);

		static::assertTrue($this->aclValidator->canCreate(1));
	}

	#[Group('units')]
	public function testCanCreateAsSubAdmin(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->aclHelperStub->method('isSubAdmin')->willReturnMap([[1, 'templates', true]]);

		static::assertTrue($this->aclValidator->canCreate(1));
	}

	#[Group('units')]
	public function testCanCreateAsUser(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->aclHelperStub->method('isSubAdmin')->willReturn(false);

		static::assertFalse($this->aclValidator->canCreate(1));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateAsModuleAdmin(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturnMap([[1, 'templates', true]]);

		static::assertTrue($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateInEdgeEdition(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->aclHelperStub->method('isSubAdmin')->willReturn(true);
		$this->aclHelperStub->method('hasSubAdminAccessOnCompany')->willReturn(true);
		$this->edition(Config::PLATFORM_EDITION_EDGE);

		// the sub admin with access on the company does not count in the edge edition
		static::assertFalse($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateAsSubAdminWithCompanyAccess(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->edition(Config::PLATFORM_EDITION_ENTERPRISE);
		$this->aclHelperStub->method('isSubAdmin')->willReturnMap([[1, 'templates', true]]);
		$this->aclHelperStub->method('hasSubAdminAccessOnCompany')->willReturnMap([[1, 4, 'templates', true]]);

		static::assertTrue($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsAllowedToDeleteTemplateAsSubAdminWithoutCompanyAccess(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->edition(Config::PLATFORM_EDITION_ENTERPRISE);
		$this->aclHelperStub->method('isSubAdmin')->willReturn(true);
		$this->aclHelperStub->method('hasSubAdminAccessOnCompany')->willReturn(false);

		static::assertFalse($this->aclValidator->isAllowedToDeleteTemplate(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsTemplateEditableAsAdmin(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(true);

		static::assertTrue($this->aclValidator->isTemplateEditable(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsTemplateEditableAsEditorWithAccess(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->edition(Config::PLATFORM_EDITION_EDGE);
		$this->aclHelperStub->method('isEditor')->willReturnMap([[1, 'templates', true]]);
		$this->aclHelperStub->method('hasEditorAccessOnUnit')->willReturnMap([[1, 12, 'templates', true]]);

		static::assertTrue($this->aclValidator->isTemplateEditable(1, $this->template));
	}

	/**
	 * Editor of the module, but not for this template.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsTemplateEditableAsEditorWithoutAccessOnThisTemplate(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->edition(Config::PLATFORM_EDITION_EDGE);
		$this->aclHelperStub->method('isEditor')->willReturn(true);
		$this->aclHelperStub->method('hasEditorAccessOnUnit')->willReturnMap([[1, 99, 'templates', true], [1, 12, 'templates', false]]);

		static::assertFalse($this->aclValidator->isTemplateEditable(1, $this->template));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsTemplateEditableWithoutRights(): void
	{
		$this->aclHelperStub->method('isModuleAdmin')->willReturn(false);
		$this->edition(Config::PLATFORM_EDITION_EDGE);
		$this->aclHelperStub->method('isEditor')->willReturn(false);
		$this->aclHelperStub->method('hasEditorAccessOnUnit')->willReturn(true);

		static::assertFalse($this->aclValidator->isTemplateEditable(1, $this->template));
	}

	private function edition(string $edition): void
	{
		$config = $this->createStub(Config::class);
		$config->method('getEdition')->willReturn($edition);
		$this->aclHelperStub->method('getConfig')->willReturn($config);
	}
}
