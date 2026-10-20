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


namespace Tests\Unit\Modules\Playlists\Helper\ConditionalPlay;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Modules\Playlists\Helper\ConditionalPlay\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	private AdapterInterface&MockObject $templateMock;
	private Translator&MockObject $translatorMock;
	private TemplatePreparer $templatePreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->templateMock     = $this->createMock(AdapterInterface::class);
		$this->translatorMock   = $this->createMock(Translator::class);
		$this->templatePreparer = new TemplatePreparer($this->translatorMock, $this->templateMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareWithFullData(): void
	{
		$conditionalPlayData = [
			'date' => ['from' => '2025-08-01', 'until' => '2025-08-31'],
			'time' => ['from' => '08:00', 'until' => '18:00'],
			'weekdays' => [1 => true, 2 => true]
		];

		$this->translatorMock->expects($this->exactly(6))->method('translate')
			->willReturnMap([
				['conditional_play', 'playlists', [], 'Translated Conditional Play'],
				['validity_period', 'playlists', [], 'Translated Validity Period'],
				['daily', 'main', [], 'Translated Daily'],
				['weekdays', 'main', [], 'Translated Weekdays'],
				['from', 'main', [], 'Translated From'],
				['until', 'main', [], 'Translated Until']
			]);

		$this->translatorMock->expects($this->once())->method('translateArrayForOptions')
			->with('weekday_selects', 'main')
			->willReturn([1 => 'Monday', 2 => 'Tuesday']);

		$this->templatePreparer->prepare(123, $conditionalPlayData);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareWithPartialData(): void
	{
		$conditionalPlayData = [
			'date' => ['from' => '2025-08-01'],
			'time' => [],
			'weekdays' => []
		];

		$this->translatorMock->expects($this->exactly(6))->method('translate')
			->willReturnMap([
				['conditional_play', 'playlists', [], 'Translated Conditional Play'],
				['validity_period', 'playlists', [], 'Translated Validity Period'],
				['daily', 'main', [], 'Translated Daily'],
				['weekdays', 'main', [], 'Translated Weekdays'],
				['from', 'main', [], 'Translated From'],
				['until', 'main', [], 'Translated Until']
			]);

		$this->translatorMock->expects($this->once())->method('translateArrayForOptions')
			->with('weekday_selects', 'main')
			->willReturn([1 => 'Monday', 2 => 'Tuesday']);

		$this->templatePreparer->prepare(456, $conditionalPlayData);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareWithEmptyData(): void
	{
		$conditionalPlayData = [];

		$this->translatorMock->expects($this->exactly(6))->method('translate')
			->willReturnMap([
				['conditional_play', 'playlists', [], 'Translated Conditional Play'],
				['validity_period', 'playlists', [], 'Translated Validity Period'],
				['daily', 'main', [], 'Translated Daily'],
				['weekdays', 'main', [], 'Translated Weekdays'],
				['from', 'main', [], 'Translated From'],
				['until', 'main', [], 'Translated Until']
			]);

		$this->translatorMock->expects($this->once())->method('translateArrayForOptions')
			->with('weekday_selects', 'main')
			->willReturn([1 => 'Monday', 2 => 'Tuesday']);

		$this->templatePreparer->prepare(789, $conditionalPlayData);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareWithWeekdays(): void
	{
		$conditionalPlayData = [
			'date' => [],
			'time' => [],
			'weekdays' => [4 => true]
		];

		$this->translatorMock->expects($this->exactly(6))->method('translate')
			->willReturnMap([
				['conditional_play', 'playlists', [], 'Translated Conditional Play'],
				['validity_period', 'playlists', [], 'Translated Validity Period'],
				['daily', 'main', [], 'Translated Daily'],
				['weekdays', 'main', [], 'Translated Weekdays'],
				['from', 'main', [], 'Translated From'],
				['until', 'main', [], 'Translated Until']
			]);

		$this->translatorMock->expects($this->once())->method('translateArrayForOptions')
			->with('weekday_selects', 'main')
			->willReturn([1 => 'Monday', 4 => 'Thursday']);

		$this->templatePreparer->prepare(101, $conditionalPlayData);
	}

	#[Group('units')]
	public function testRenderFullData(): void
	{

		$this->templateMock->expects($this->once())->method('render')
			->with('playlists/conditional-play', [])
			->willReturn('Rendered Template');

		$result = $this->templatePreparer->render();

		self::assertSame('Rendered Template', $result);
	}
}
