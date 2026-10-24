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

namespace Tests\Unit\Framework\Dashboards;

use App\Framework\Dashboards\SystemDashboard;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\System\SystemStats;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class SystemDashboardTest extends TestCase
{
	private SystemStats&Stub $systemStatsStub;
	private Translator&Stub $translatorStub;
	private SystemDashboard $systemDashboard;

	protected function setUp(): void
	{
		parent::setUp();

		$this->systemStatsStub = $this->createStub(SystemStats::class);
		$this->translatorStub  = $this->createStub(Translator::class);
		$this->translatorStub->method('translate')->willReturnMap([
			['system_dashboard', 'main', [], 'System Dashboard'],
			['memory_used', 'main', [], '%s of %s'],
			['disc_used', 'main', [], '%s of %s (%s)'],
			['system_load', 'main', [], 'System Load'],
			['memory_usage', 'main', [], 'Memory Usage'],
			['disc_usage', 'main', [], 'Disc Usage'],
		]);

		$this->systemDashboard = new SystemDashboard($this->systemStatsStub, $this->translatorStub);
	}

	#[Group('units')]
	public function testGetIdReturnsCorrectValue(): void
	{
		static::assertSame('system', $this->systemDashboard->getId());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetTitleReturnsTranslatedValue(): void
	{
		static::assertSame('System Dashboard', $this->systemDashboard->getTitle());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testRenderContentReturnsCorrectHtml(): void
	{
		$this->systemStatsStub->method('getLoadData')->willReturn(['1_min' => '1.23', '5_min' => '4.56', '15_min' => '7.89']);
		$this->systemStatsStub->method('getRamStats')->willReturn(['total' => 8000, 'used' => 2000, 'free' => 6000]);
		$this->systemStatsStub->method('getDiscInfo')->willReturn(['size' => '500G', 'used' => '100G', 'available' => '400G', 'percent' => '20%']);

		$expected = '<ul>
	<li><strong>System Load:</strong><span>1.23 | 4.56 | 7.89</span></li>
	<li><strong>Memory Usage:</strong><span>2000 of 8000</span></li>
	<li><strong>Disc Usage:</strong><span>100G of 500G (20%)</span></li>
</ul>';

		static::assertSame($expected, $this->systemDashboard->renderContent());
	}

	/**
	 * Without Linux SystemStats has only its default values. The dashboard must still render.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testRenderContentWithDefaultStatsStillRenders(): void
	{
		$this->systemStatsStub->method('getLoadData')->willReturn(['1_min' => '', '5_min' => '', '15_min' => '']);
		$this->systemStatsStub->method('getRamStats')->willReturn(['total' => 0, 'used' => 0, 'free' => 0]);
		$this->systemStatsStub->method('getDiscInfo')->willReturn(['size' => '', 'used' => '', 'available' => '', 'percent' => '']);

		$expected = '<ul>
	<li><strong>System Load:</strong><span> |  | </span></li>
	<li><strong>Memory Usage:</strong><span>0 of 0</span></li>
	<li><strong>Disc Usage:</strong><span> of  ()</span></li>
</ul>';

		static::assertSame($expected, $this->systemDashboard->renderContent());
	}
}
