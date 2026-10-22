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

use App\Framework\System\SystemStats;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Dashboards\SystemDashboard;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SystemDashboardTest extends TestCase
{
	private SystemStats&MockObject $systemStatsMock;
	private Translator&MockObject $translatorMock;
	private SystemDashboard $systemDashboard;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->systemStatsMock = $this->createMock(SystemStats::class);
		$this->translatorMock = $this->createMock(Translator::class);

		$this->systemDashboard = new SystemDashboard($this->systemStatsMock, $this->translatorMock);
	}

	#[Group('units')]
	public function testGetIdReturnsCorrectValue(): void
	{
		$this->systemStatsMock->expects($this->never())->method($this->anything());
		$this->translatorMock->expects($this->never())->method($this->anything());
		$result = $this->systemDashboard->getId();

		static::assertSame('system', $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetTitleReturnsCorrectTranslatedValue(): void
	{
		$this->systemStatsMock->expects($this->never())->method($this->anything());
		$this->translatorMock
			->method('translate')
			->with('system_dashboard', 'main')
			->willReturn('System Dashboard');

		$result = $this->systemDashboard->getTitle();

		static::assertSame('System Dashboard', $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testRenderContentReturnsCorrectHtml(): void
	{
		$this->systemStatsMock
			->expects($this->once())
			->method('determineSystemStats');

		$this->systemStatsMock
			->method('getLoadData')
			->willReturn(['1.23', '4.56', '7.89']);

		$this->systemStatsMock
			->method('getRamStats')
			->willReturn(['used' => '2GB', 'total' => '8GB']);

		$this->systemStatsMock
			->method('getDiscInfo')
			->willReturn(['used' => '100GB', 'size' => '500GB', 'percent' => '20%']);

		$this->translatorMock
			->expects($this->atLeastOnce())->method('translate')
			->willReturnMap([
				['memory_used', 'main', [], '2GB of 8GB'],
				['disc_used', 'main', [], '%s of %s (%s)'],
				['system_load', 'main', [], 'System Load'],
				['memory_usage', 'main', [], 'Memory Usage'],
				['disc_usage', 'main', [], 'Disc Usage'],
			]);

		$result = $this->systemDashboard->renderContent();

		$expected = '<ul>
	<li><strong>System Load:</strong><span>1.23 | 4.56 | 7.89</span></li>
	<li><strong>Memory Usage:</strong><span>2GB of 8GB</span></li>
	<li><strong>Disc Usage:</strong><span>100GB of 500GB (20%)</span></li>
</ul>';

		static::assertSame($expected, $result);
	}
}
