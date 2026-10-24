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

namespace Tests\Integration\Framework\System;

use App\Framework\Exceptions\CoreException;
use App\Framework\System\ShellExecutor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The shell executor starts real processes, this is all it does.
 */
#[Group('io')]
class ShellExecutorTest extends TestCase
{
	private ShellExecutor $executor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->executor = new ShellExecutor();
	}

	public function testSetCommandIsChainable(): void
	{
		static::assertSame($this->executor, $this->executor->setCommand('echo hi'));
	}

	/**
	 * @throws CoreException
	 */
	public function testExecuteReturnsTheLinesAndTheExitCode(): void
	{
		$result = $this->executor->setCommand('echo "Hello, World!"')->execute();

		static::assertSame(['output' => ['Hello, World!'], 'code' => 0], $result);
	}

	/**
	 * @throws CoreException
	 */
	public function testExecuteReturnsEveryLineAndMergesStderr(): void
	{
		$result = $this->executor->setCommand("sh -c 'echo one; echo two 1>&2; echo three'")->execute();

		static::assertSame(['one', 'two', 'three'], $result['output']);
	}

	public function testExecuteFailsForANonZeroExitCodeWithTheOutputInTheMessage(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage("Command failed: sh -c 'echo oops; exit 3' \noutput: oops");

		$this->executor->setCommand("sh -c 'echo oops; exit 3'")->execute();
	}

	public function testExecuteFailsForAnUnknownCommand(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessageMatches('/^Command failed: hurz/');

		$this->executor->setCommand('hurz')->execute();
	}

	public function testExecuteWithoutACommandThrows(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('No command set for execution.');

		$this->executor->execute();
	}

	public function testALaterCommandReplacesTheEarlierOne(): void
	{
		$this->executor->setCommand('echo first');

		$result = $this->executor->setCommand('echo second')->execute();

		static::assertSame(['second'], $result['output']);
	}

	/**
	 * @throws CoreException
	 */
	public function testExecuteSimpleReturnsTheWholeOutput(): void
	{
		static::assertSame("Hello, World!\n", $this->executor->setCommand('echo "Hello, World!"')->executeSimple());
		static::assertSame("one\ntwo\n", $this->executor->setCommand('echo one; echo two')->executeSimple());
	}

	/**
	 * The redirect (2>&1) is appended to the command: for several commands in a row it applies only to the
	 * last one, so a command which needs the errors of all of them has to be wrapped, like sh -c '...'.
	 *
	 * @throws CoreException
	 */
	public function testExecuteSimpleMergesStderr(): void
	{
		static::assertSame("out\nerr\n", $this->executor->setCommand("sh -c 'echo out; echo err 1>&2'")->executeSimple());
	}

	/**
	 * The exit code is not looked at: the error text of a failing command is returned as output.
	 *
	 * @throws CoreException
	 */
	public function testExecuteSimpleReturnsTheErrorTextOfAFailingCommand(): void
	{
		static::assertSame("oops\n", $this->executor->setCommand("sh -c 'echo oops; exit 3'")->executeSimple());
		static::assertStringContainsString('hurz', $this->executor->setCommand('hurz')->executeSimple());
	}

	/**
	 * shell_exec() gives null for a command without output, which is reported as failure.
	 */
	public function testExecuteSimpleFailsForACommandWithoutOutput(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Command failed: true');

		$this->executor->setCommand('true')->executeSimple();
	}

	public function testExecuteSimpleWithoutACommandThrows(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('No command set for execution.');

		$this->executor->executeSimple();
	}
}
