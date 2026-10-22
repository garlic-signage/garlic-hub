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

namespace Tests\Unit\Modules\Users\Helper\InitialAdmin;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Users\Helper\InitialAdmin\FormInputHandler;
use App\Modules\Users\Helper\InitialAdmin\Orchestrator;
use App\Modules\Users\Helper\InitialAdmin\SettingsFormBuilder;
use App\Modules\Users\Services\UsersAdminCreateService;
use Doctrine\DBAL\Exception;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	use PHPMock;

	private UsersAdminCreateService&MockObject $serviceMock;
	private FormInputHandler&MockObject $inputHandlerMock;
	private SettingsFormBuilder&MockObject $formBuilderMock;
	private Config&MockObject $configMock;
	private Translator&MockObject $translatorMock;
	private Orchestrator $orchestrator;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->serviceMock      = $this->createMock(UsersAdminCreateService::class);
		$this->inputHandlerMock = $this->createMock(FormInputHandler::class);
		$this->formBuilderMock  = $this->createMock(SettingsFormBuilder::class);
		$this->configMock       = $this->createMock(Config::class);
		$this->translatorMock   = $this->createMock(Translator::class);

		$this->orchestrator = new Orchestrator(
			$this->serviceMock,
			$this->inputHandlerMock,
			$this->formBuilderMock,
			$this->configMock,
			$this->translatorMock
		);
	}

	/**
	 * @throws Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testIsFunctionAllowedWithExistingLockFile(): void
	{
		$this->expectUntouched($this->serviceMock, $this->inputHandlerMock, $this->formBuilderMock, $this->configMock, $this->translatorMock);
		define('INSTALL_LOCK_FILE', 'INSTALL_LOCK_FILE');
		$fileExistsMock = $this->getFunctionMock('App\Modules\Users\Helper\InitialAdmin', 'file_exists');
		$fileExistsMock->expects($this->once())->with('INSTALL_LOCK_FILE')->willReturn(true);

		static::assertFalse($this->orchestrator->isFunctionAllowed());
	}

	/**
	 * @throws Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testIsFunctionAllowedWithAdminUserCreatesLockFile(): void
	{
		$this->expectUntouched($this->inputHandlerMock, $this->formBuilderMock, $this->configMock, $this->translatorMock);
		define('INSTALL_LOCK_FILE', 'INSTALL_LOCK_FILE');
		$fileExistsMock = $this->getFunctionMock('App\Modules\Users\Helper\InitialAdmin', 'file_exists');
		$fileExistsMock->expects($this->once())->with('INSTALL_LOCK_FILE')->willReturn(false);
		$this->serviceMock->expects($this->once())->method('hasAdminUser')->willReturn(true);
		$this->serviceMock->expects($this->once())->method('creatLockfile');
		$this->serviceMock->expects($this->once())->method('logAlarm');

		static::assertFalse($this->orchestrator->isFunctionAllowed());
	}

	/**
	 * @throws Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testIsFunctionAllowed(): void
	{
		$this->expectUntouched($this->inputHandlerMock, $this->formBuilderMock, $this->configMock, $this->translatorMock);
		define('INSTALL_LOCK_FILE', 'INSTALL_LOCK_FILE');
		$fileExistsMock = $this->getFunctionMock('App\Modules\Users\Helper\InitialAdmin', 'file_exists');
		$fileExistsMock->expects($this->once())->with('INSTALL_LOCK_FILE')->willReturn(false);
		$this->serviceMock->expects($this->once())->method('hasAdminUser')->willReturn(false);
		$this->serviceMock->expects($this->never())->method('creatLockfile');

		static::assertTrue($this->orchestrator->isFunctionAllowed());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildForm(): void
	{
		$this->expectUntouched($this->serviceMock, $this->inputHandlerMock, $this->translatorMock);
		$form = ['hidden' => [], 'visible' => []];
		$this->expectPasswordPattern();
		$this->formBuilderMock->expects($this->once())->method('buildForm')
			->with(['username' => 'admin'], 'pattern')->willReturn($form);

		static::assertSame($form, $this->orchestrator->buildForm(['username' => 'admin']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->serviceMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectPasswordPattern();
		$this->inputHandlerMock->expects($this->once())->method('validate')
			->with(['username' => ''], 'pattern')->willReturn(['No username.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');

		static::assertSame(
			['success' => false, 'errors' => ['No username.']],
			$this->orchestrator->store(['username' => ''])
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreReturnsTranslatedServiceError(): void
	{
		$this->expectUntouched($this->formBuilderMock);
		$this->expectPasswordPattern();
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn(['username' => 'admin']);
		$exception = new TranslatableModuleException('users', 'admin_exists');
		$this->serviceMock->expects($this->once())->method('insertNewAdminUser')
			->with(['username' => 'admin'], $this->configMock)->willThrowException($exception);
		$this->translatorMock->expects($this->once())->method('translateException')
			->with($exception)->willReturn('User exists.');

		static::assertSame(
			['success' => false, 'errors' => ['User exists.']],
			$this->orchestrator->store(['username' => 'admin'])
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStore(): void
	{
		$this->expectUntouched($this->formBuilderMock, $this->translatorMock);
		$this->expectPasswordPattern();
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn(['username' => 'admin']);
		$this->serviceMock->expects($this->once())->method('insertNewAdminUser')->willReturn(1);

		static::assertSame(['success' => true], $this->orchestrator->store(['username' => 'admin']));
	}

	private function expectPasswordPattern(): void
	{
		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('password_pattern', 'main')->willReturn('pattern');
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
