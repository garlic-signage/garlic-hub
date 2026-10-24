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

namespace Tests\Integration\Framework\Dashboards;

use App\Framework\Dashboards\SystemDashboard;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\System\ShellExecutor;
use App\Framework\System\SystemStats;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * SystemDashboard with the real SystemStats. Only the shell is replaced by fixed output of free and df,
 * so the test shows what the dashboard makes of what the system reports.
 */
#[Group('io')]
class SystemDashboardTest extends TestCase
{
	private const string FREE = "              total        used        free\nMem:          8000        2000        6000\nSwap:            0           0           0";
	private const string DF   = "Filesystem      Size  Used Avail Use% Mounted on\n/dev/sda1       100G   40G   60G  40% /\ntotal           500G  100G  400G  20% -";

	private SystemStats $systemStats;
	private SystemDashboard $systemDashboard;

	protected function setUp(): void
	{
		parent::setUp();

		$shell = new class(self::FREE, self::DF) extends ShellExecutor
		{
			private string $executed = '';

			public function __construct(private readonly string $free, private readonly string $df)
			{
				parent::__construct();
			}

			public function setCommand(string $command): static
			{
				$this->executed = $command;
				return parent::setCommand($command);
			}

			public function executeSimple(): string
			{
				return match ($this->executed)
				{
					'free -m'        => $this->free,
					'df -h --total'  => $this->df,
					default          => throw new CoreException('Unexpected command: ' . $this->executed),
				};
			}
		};

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnMap([
			['memory_used', 'main', [], '%s of %s'],
			['disc_used', 'main', [], '%s of %s (%s)'],
			['system_load', 'main', [], 'System Load'],
			['memory_usage', 'main', [], 'Memory Usage'],
			['disc_usage', 'main', [], 'Disc Usage'],
		]);

		$this->systemStats     = new SystemStats($shell);
		$this->systemDashboard = new SystemDashboard($this->systemStats, $translator);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testRenderContentShowsWhatTheSystemReports(): void
	{
		$this->systemStats->setIsLinux(true);

		$html = $this->systemDashboard->renderContent();

		static::assertStringContainsString('<li><strong>Memory Usage:</strong><span>2000 of 8000</span></li>', $html);
		static::assertStringContainsString('<li><strong>Disc Usage:</strong><span>100G of 500G (20%)</span></li>', $html);
		static::assertMatchesRegularExpression('#<li><strong>System Load:</strong><span>\d+\.\d\d \| \d+\.\d\d \| \d+\.\d\d</span></li>#', $html);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testRenderContentOnANonLinuxSystemShowsOnlyDefaults(): void
	{
		$this->systemStats->setIsLinux(false);

		$html = $this->systemDashboard->renderContent();

		static::assertStringContainsString('<span>0 of 0</span>', $html);
		static::assertStringContainsString('<span> of  ()</span>', $html);
	}
}
