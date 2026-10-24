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
use App\Framework\System\SystemStats;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * SystemStats with the real shell on this machine. The numbers are those of the machine, so only their
 * form and plausibility is checked. Skipped where free and df are not available.
 */
#[Group('io')]
class SystemStatsTest extends TestCase
{
	private SystemStats $systemStats;

	protected function setUp(): void
	{
		parent::setUp();

		if (PHP_OS !== 'Linux' || trim((string) shell_exec('command -v free df 2>/dev/null')) === '')
			self::markTestSkipped('free and df of Linux are needed.');

		$this->systemStats = new SystemStats(new ShellExecutor());
	}

	/**
	 * @throws CoreException
	 */
	public function testTheStatsOfThisMachineAreRead(): void
	{
		$this->systemStats->determineSystemStats();

		$ram = $this->systemStats->getRamStats();
		static::assertGreaterThan(0, $ram['total']);
		static::assertGreaterThan(0, $ram['used']);
		static::assertLessThanOrEqual($ram['total'], $ram['used']);

		$disc = $this->systemStats->getDiscInfo();
		static::assertMatchesRegularExpression('/^[\d.,]+[KMGTP]?$/', $disc['size']);
		static::assertMatchesRegularExpression('/^\d+%$/', $disc['percent']);

		foreach ($this->systemStats->getLoadData() as $load)
		{
			static::assertMatchesRegularExpression('/^\d+\.\d\d$/', $load);
		}
	}
}
