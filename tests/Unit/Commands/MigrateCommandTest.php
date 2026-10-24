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

namespace Tests\Unit\Commands;

use App\Commands\MigrateCommand;
use App\Framework\Database\Migration\Runner;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Exception as DbalException;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

class MigrateCommandTest extends TestCase
{
	private Runner&MockObject $runnerMock;
	private CommandTester $tester;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->runnerMock = $this->createMock(Runner::class);
		$this->tester     = new CommandTester(new MigrateCommand($this->runnerMock));
	}

	#[Group('units')]
	public function testMigrationSuccess(): void
	{
		$this->runnerMock->expects($this->once())->method('execute')->with(null);
		$this->runnerMock->expects($this->never())->method('rollback');
		$this->runnerMock->method('isApplied')->willReturn(true);

		$this->tester->execute([]);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertStringContainsString('Migration succeed.', $this->tester->getDisplay());
	}

	#[Group('units')]
	public function testMigrationPassesVersion(): void
	{
		$this->runnerMock->expects($this->once())->method('execute')->with(20240101);
		$this->runnerMock->method('isApplied')->willReturn(true);

		$this->tester->execute(['version' => '20240101']);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
	}

	#[Group('units')]
	public function testRollbackSuccess(): void
	{
		$this->runnerMock->expects($this->once())->method('rollback')->with(null);
		$this->runnerMock->expects($this->never())->method('execute');
		$this->runnerMock->method('isApplied')->willReturn(true);

		$this->tester->execute(['--rollback' => true]);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertStringContainsString('Migration succeed.', $this->tester->getDisplay());
	}

	#[Group('units')]
	public function testRollbackShortOptionPassesVersion(): void
	{
		$this->runnerMock->expects($this->once())->method('rollback')->with(20240101);
		$this->runnerMock->expects($this->never())->method('execute');
		$this->runnerMock->method('isApplied')->willReturn(true);

		$this->tester->execute(['-r' => true, 'version' => '20240101']);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
	}

	#[Group('units')]
	#[DataProvider('migrationExceptionProvider')]
	public function testMigrationFailsWithException(Throwable $exception): void
	{
		$this->runnerMock->expects($this->once())->method('execute')->willThrowException($exception);

		$this->tester->execute([]);

		static::assertSame(Command::FAILURE, $this->tester->getStatusCode());
		static::assertStringContainsString('Migration failed: Test Exception', $this->tester->getDisplay());
	}

	#[Group('units')]
	#[DataProvider('migrationExceptionProvider')]
	public function testRollbackFailsWithException(Throwable $exception): void
	{
		$this->runnerMock->expects($this->once())->method('rollback')->willThrowException($exception);

		$this->tester->execute(['--rollback' => true]);

		static::assertSame(Command::FAILURE, $this->tester->getStatusCode());
		static::assertStringContainsString('Migration failed: Test Exception', $this->tester->getDisplay());
	}

	#[Group('units')]
	#[DataProvider('invalidVersionProvider')]
	public function testInvalidVersionIsRejected(string $version, bool $rollback): void
	{
		$this->runnerMock->expects($this->never())->method('execute');
		$this->runnerMock->expects($this->never())->method('rollback');

		$this->tester->execute(['version' => $version, '--rollback' => $rollback]);

		static::assertSame(Command::FAILURE, $this->tester->getStatusCode());
		static::assertStringContainsString('Invalid version "' . $version . '"', $this->tester->getDisplay());
	}

	#[Group('units')]
	public function testNoMigrationsFound(): void
	{
		$this->runnerMock->expects($this->once())->method('execute')->with(null);
		$this->runnerMock->method('isApplied')->willReturn(false);

		$this->tester->execute([]);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertStringContainsString('No migrations found to apply.', $this->tester->getDisplay());
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function invalidVersionProvider(): array
	{
		return [
			'text'              => ['abc', false],
			'text on rollback'  => ['abc', true],
			'negative'          => ['-1', false],
			'decimal'           => ['1.5', false],
			'number with text'  => ['2x', false],
		];
	}

	/**
	 * @return array<string, array{Throwable}>
	 */
	public static function migrationExceptionProvider(): array
	{
		return [
			'database'   => [new DatabaseException('Test Exception')],
			'dbal'       => [new class('Test Exception') extends \RuntimeException implements DbalException {}],
			'filesystem' => [new class('Test Exception') extends \RuntimeException implements FilesystemException {}],
		];
	}
}
