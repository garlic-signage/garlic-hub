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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Repositories\TemplatesRepository;
use App\Modules\Templates\Services\AclValidator;
use App\Modules\Templates\Services\TemplatesService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class TemplatesServiceTest extends TestCase
{
	private TemplatesRepository&MockObject $repositoryMock;
	private AclValidator&MockObject $aclValidatorMock;
	private LoggerInterface&MockObject $loggerMock;
	private TemplatesService $service;

	/** @var array{UID: int, company_id: int, template_id: int, name: string} */
	private array $template = ['UID' => 5, 'company_id' => 1, 'template_id' => 3, 'name' => 'Template'];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->repositoryMock   = $this->createMock(TemplatesRepository::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->loggerMock       = $this->createMock(LoggerInterface::class);
		$userSessionStub        = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(5);

		$this->service = new TemplatesService($this->repositoryMock, $this->aclValidatorMock, $userSessionStub, $this->loggerMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCheckCreateRights(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('canCreate')->with(5)->willReturn(true);
		$this->repositoryMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertTrue($this->service->checkCreateRights());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCheckCreateRightsDenied(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('canCreate')->with(5)->willReturn(false);
		$this->repositoryMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertFalse($this->service->checkCreateRights());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUpdateTakesOnlySettingsFields(): void
	{
		$post = ['UID' => 8, 'content' => '{}', 'name' => 'New', 'visibility' => 'public', 'type' => 'canvas', 'csrf_token' => 'x'];

		$this->repositoryMock->expects($this->once())->method('update')
			->with(3, ['UID' => 8, 'content' => '{}', 'name' => 'New', 'visibility' => 'public'])
			->willReturn(1);
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertSame(1, $this->service->update(3, $post));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUpdateWithNameOnly(): void
	{
		$this->repositoryMock->expects($this->once())->method('update')
			->with(3, ['name' => 'New'])
			->willReturn(1);
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertSame(1, $this->service->update(3, ['name' => 'New']));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertWithOwner(): void
	{
		$post = ['UID' => 8, 'type' => 'canvas', 'name' => 'New', 'visibility' => 'private', 'content' => 'ignored'];

		$this->repositoryMock->expects($this->once())->method('insert')
			->with(['UID' => 8, 'type' => 'canvas', 'name' => 'New', 'visibility' => 'private'])
			->willReturn('17');
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertSame(17, $this->service->insert($post));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertWithoutOwnerUsesSessionUser(): void
	{
		$this->repositoryMock->expects($this->once())->method('insert')
			->with(['UID' => 5, 'type' => 'canvas', 'name' => 'New'])
			->willReturn(18);
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertSame(18, $this->service->insert(['type' => 'canvas', 'name' => 'New']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testLoadWithUserById(): void
	{
		$this->repositoryMock->expects($this->once())->method('findFirstWithUserName')->with(3)->willReturn($this->template);
		$this->aclValidatorMock->expects($this->once())->method('isTemplateEditable')->with(5, $this->template)->willReturn(true);
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertSame($this->template, $this->service->loadWithUserById(3));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testLoadWithUserByIdNotFound(): void
	{
		$this->repositoryMock->expects($this->once())->method('findFirstWithUserName')->with(3)->willReturn([]);
		$this->aclValidatorMock->expects($this->never())->method('isTemplateEditable');
		$this->loggerMock->expects($this->never())->method($this->anything());

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('No template with Id: 3 found.');

		$this->service->loadWithUserById(3);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testLoadWithUserByIdNotEditable(): void
	{
		$this->repositoryMock->expects($this->once())->method('findFirstWithUserName')->willReturn($this->template);
		$this->aclValidatorMock->expects($this->once())->method('isTemplateEditable')->willReturn(false);
		$this->loggerMock->expects($this->never())->method($this->anything());

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Template is not editable.');

		$this->service->loadWithUserById(3);
	}

	#[Group('units')]
	public function testDelete(): void
	{
		$this->repositoryMock->expects($this->once())->method('findFirstWithUserName')->willReturn($this->template);
		$this->aclValidatorMock->expects($this->once())->method('isTemplateEditable')->willReturn(true);
		$this->repositoryMock->expects($this->once())->method('delete')->with(3)->willReturn(1);
		$this->loggerMock->expects($this->never())->method($this->anything());

		static::assertSame(1, $this->service->delete(3));
		static::assertFalse($this->service->hasErrorMessages());
	}

	#[Group('units')]
	public function testDeleteNotEditable(): void
	{
		$this->repositoryMock->expects($this->once())->method('findFirstWithUserName')->willReturn($this->template);
		$this->aclValidatorMock->expects($this->once())->method('isTemplateEditable')->willReturn(false);
		$this->repositoryMock->expects($this->never())->method('delete');
		$this->loggerMock->expects($this->once())->method('error')->with('Error delete template: Template is not editable.');

		static::assertSame(0, $this->service->delete(3));
		static::assertSame(['Template is not editable.'], $this->service->getErrorMessages());
	}

	#[Group('units')]
	public function testDeleteFailsInRepository(): void
	{
		$this->repositoryMock->expects($this->once())->method('findFirstWithUserName')->willReturn($this->template);
		$this->aclValidatorMock->expects($this->once())->method('isTemplateEditable')->willReturn(true);
		$this->repositoryMock->expects($this->once())->method('delete')->willThrowException(new RuntimeException('db gone'));
		$this->loggerMock->expects($this->once())->method('error')->with('Error delete template: db gone');

		static::assertSame(0, $this->service->delete(3));
		static::assertSame(['db gone'], $this->service->getErrorMessages());
	}
}
