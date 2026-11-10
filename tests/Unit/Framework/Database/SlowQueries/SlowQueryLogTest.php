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

namespace Tests\Unit\Framework\Database\SlowQueries;

use App\Framework\Database\SlowQueries\SlowQueryLog;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The clock is a list of times in milliseconds: the start and the end of each query.
 */
class SlowQueryLogTest extends TestCase
{
	private TestHandler $handler;
	/** @var array<string,mixed> */
	private array $server;

	protected function setUp(): void
	{
		parent::setUp();
		$this->handler = new TestHandler();
		$this->server  = $_SERVER;
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;
		parent::tearDown();
	}

	/**
	 * @param list<float> $times
	 */
	private function log(int $thresholdMs, array $times): SlowQueryLog
	{
		return new SlowQueryLog(new Logger('test', [$this->handler]), $thresholdMs, static function () use (&$times): float {
			return (float) array_shift($times);
		});
	}

	/**
	 * @return list<string>
	 */
	private function messages(): array
	{
		return array_values(array_map(static fn($record): string => $record->level->getName() . ' ' . strtr($record->message, [
			'{duration}' => (string) $record->context['duration'], '{path}' => (string) $record->context['path'], '{sql}' => (string) $record->context['sql']
		]), $this->handler->getRecords()));
	}

	#[Group('units')]
	public function testOnlyTheQueriesFromTheThresholdOnAreWritten(): void
	{
		$_SERVER['REQUEST_URI'] = '/mediapool?node=3';
		$log = $this->log(100, [0.0, 99.4, 1000.0, 1100.0, 2000.0, 2250.6]);

		static::assertSame('fast', $log->measure('SELECT 1', static fn(): string => 'fast'));
		$log->measure('SELECT 2', static fn(): string => 'just slow');
		$log->measure('SELECT 3', static fn(): string => 'slow');

		static::assertSame(['WARNING 100 ms /mediapool: SELECT 2', 'WARNING 251 ms /mediapool: SELECT 3'], $this->messages());
	}

	#[Group('units')]
	public function testAFailedQueryIsMeasuredToo(): void
	{
		unset($_SERVER['REQUEST_URI']);
		$log = $this->log(100, [0.0, 300.0]);

		$caught = '';
		try
		{
			$log->measure('SELECT broken', static fn(): never => throw new \RuntimeException('broken'));
		}
		catch (\RuntimeException $e)
		{
			$caught = $e->getMessage();
		}

		static::assertSame('broken', $caught, 'The exception goes on.');

		static::assertSame(['WARNING 300 ms cli: SELECT broken'], $this->messages());
	}

	#[Group('units')]
	public function testTheRealClockMeasuresMilliseconds(): void
	{
		$log = new SlowQueryLog(new Logger('test', [$this->handler]), 20);

		$log->measure('SELECT fast', static fn(): int => 1);
		$log->measure('SELECT slow', static function (): int { usleep(30_000); return 1; });

		static::assertCount(1, $this->handler->getRecords());
		static::assertSame('SELECT slow', $this->handler->getRecords()[0]->context['sql']);
		static::assertGreaterThanOrEqual(20, $this->handler->getRecords()[0]->context['duration']);
	}
}
