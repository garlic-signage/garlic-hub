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

namespace Tests\Unit\Framework\System;

use App\Framework\System\ShellExecutor;
use App\Framework\Exceptions\CoreException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class ShellExecutorTest extends TestCase
{
	use PHPMock;
	private ShellExecutor $executor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->executor   = new ShellExecutor();
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testExecuteWithValidCommand(): void
	{
		$this->executor->setCommand('echo "Hello, World!"');
		$result = $this->executor->execute();

		static::assertEquals(0, $result['code']);
		static::assertEquals('Hello, World!', $result['output'][0]);
	}

	#[Group('units')]
	public function testExecuteWithoutCommandThrowsException(): void
	{
		$this->expectException(CoreException::class);
		$this->executor->execute();
	}

	#[Group('units')]
	public function testLoggerCalledOnError(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessageMatches('*Command failed*');

		$this->executor->setCommand('hurz');
		$this->executor->execute();
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testExecuteSimpleWithValidCommand(): void
	{
		$this->executor->setCommand('echo "Hello, World!"');
		$result = $this->executor->executeSimple();

		static::assertEquals("Hello, World!\n", $result);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testExecuteSimpleFails(): void
	{
		$shell_exec = $this->getFunctionMock('App\Framework\System', 'shell_exec');
		$shell_exec->expects($this->once())->willReturn(null);

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Command failed: prfft');

		$this->executor->setCommand('prfft');
		$this->executor->executeSimple();

	}

}
