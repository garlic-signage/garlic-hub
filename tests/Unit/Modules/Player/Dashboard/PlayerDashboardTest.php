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

namespace Tests\Unit\Modules\Player\Dashboard;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Player\Dashboard\PlayerDashboard;
use App\Modules\Player\Services\PlayerService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerDashboardTest extends TestCase
{
	private PlayerDashboard $playerDashboard;
	private PlayerService&MockObject $playerServiceMock;
	private Translator&MockObject $translatorMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerServiceMock = $this->createMock(PlayerService::class);
		$this->translatorMock = $this->createMock(Translator::class);

		$this->playerDashboard = new PlayerDashboard(
			$this->playerServiceMock,
			$this->translatorMock
		);
	}

	#[Group('units')]
	public function testGetId(): void
	{
		$this->playerServiceMock->expects($this->never())->method($this->anything());
		$this->translatorMock->expects($this->never())->method($this->anything());
		$result = $this->playerDashboard->getId();
		static::assertSame('player', $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetTitle(): void
	{
		$this->playerServiceMock->expects($this->never())->method($this->anything());
		$this->translatorMock
			->expects($this->once())
			->method('translate')
			->with('dashboard', 'player')
			->willReturn('Player Dashboard');

		$result = $this->playerDashboard->getTitle();

		static::assertSame('Player Dashboard', $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testRenderContent(): void
	{
		$data = [
			'active' => 10,
			'pending' => 5,
			'inactive' => 3,
		];

		$this->playerServiceMock
			->expects($this->once())
			->method('findAllForDashboard')
			->willReturn($data);

		$this->translatorMock
			->expects($this->exactly(3))
			->method('translate')
			->willReturnMap([
				['count_active', 'player', [], 'Active Players'],
				['count_pending', 'player', [], 'Pending Players'],
				['count_inactive', 'player', [], 'Inactive Players'],
			]);

		$result = $this->playerDashboard->renderContent();

		$expected = '<ul>
	<li><strong>Active Players:</strong><span>10</span></li>
	<li><strong>Pending Players:</strong><span>5</span></li>
	<li><strong>Inactive Players:</strong><span>3</span></li>
</ul>';

		static::assertSame($expected, $result);
	}
}
