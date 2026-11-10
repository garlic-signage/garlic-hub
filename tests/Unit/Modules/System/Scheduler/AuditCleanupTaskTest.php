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

namespace Tests\Unit\Modules\System\Scheduler;

use App\Framework\Config\Config;
use App\Modules\System\Scheduler\AuditCleanupTask;
use App\Shared\Audit\AuditLogRepository;
use App\Shared\Scheduler\TaskInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The delete with the database is tested with the repository in tests/Integration.
 */
class AuditCleanupTaskTest extends TestCase
{
	/**
	 * @return array<string,array{string,int}>
	 */
	public static function retentions(): array
	{
		return ['the setting' => ['30', 30], 'one day at least' => ['1', 1], 'not less than one day' => ['0', 1], 'no number' => ['', 1]];
	}

	#[Group('units')]
	#[DataProvider('retentions')]
	public function testTheEntriesOlderThanTheRetentionAreDeleted(string $setting, int $days): void
	{
		$configStub = $this->createStub(Config::class);
		$configStub->method('getConfigValue')->willReturnMap([['retention_days', 'system', 'Audit', $setting]]);
		$deletedBefore  = null;
		$repositoryStub = $this->createStub(AuditLogRepository::class);
		$repositoryStub->method('deleteOlderThan')->willReturnCallback(function (DateTimeImmutable $time) use (&$deletedBefore): int {
			$deletedBefore = $time;
			return 0;
		});

		new AuditCleanupTask($repositoryStub, $configStub)->run();

		static::assertInstanceOf(DateTimeImmutable::class, $deletedBefore);
		static::assertEqualsWithDelta(new DateTimeImmutable('-' . $days . ' days')->getTimestamp(), $deletedBefore->getTimestamp(), 5);
	}

	#[Group('units')]
	public function testTheTaskRunsDaily(): void
	{
		$task = new AuditCleanupTask($this->createStub(AuditLogRepository::class), $this->createStub(Config::class));

		static::assertSame('audit.cleanup', $task->name());
		static::assertSame(TaskInterval::DAILY, $task->interval());
	}
}
