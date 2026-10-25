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

use App\Framework\Localization\Translate\Translator;
use App\Modules\Playlists\Helper\ConditionalPlay\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingTemplateAdapter;

class TemplatePreparerTest extends TestCase
{
	private RecordingTemplateAdapter $template;
	private TemplatePreparer $preparer;

	protected function setUp(): void
	{
		parent::setUp();
		/** @var Translator&Stub $translator */
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday']);

		$this->template = new RecordingTemplateAdapter();
		$this->preparer = new TemplatePreparer($translator, $this->template);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function rendered(): array
	{
		static::assertSame('rendered playlists/conditional-play', $this->preparer->render());
		static::assertCount(1, $this->template->rendered);
		static::assertSame('playlists/conditional-play', $this->template->rendered[0]['template']);

		return $this->template->rendered[0]['data'];
	}

	#[Group('units')]
	public function testTheTextsAndTheValuesOfTheItem(): void
	{
		$this->preparer->prepare(123, ['date' => ['from' => '2025-08-01', 'until' => '2025-08-31'], 'time' => ['from' => '08:00', 'until' => '18:00'], 'weekdays' => [1 => ['from' => 0, 'until' => 8], 4 => ['from' => 0, 'until' => 8]]]);

		$data = $this->rendered();

		static::assertSame('playlists.conditional_play', $data['LANG_CONDITIONAL_PLAY']);
		static::assertSame('playlists.validity_period', $data['LANG_DATE_PERIOD']);
		static::assertSame(['main.daily', 'main.weekdays', 'main.from', 'main.until'], [$data['LANG_DAILY'], $data['LANG_WEEKDAYS'], $data['LANG_FROM'], $data['LANG_UNTIL']]);
		static::assertSame(123, $data['CONDITIONAL_PLAY_ITEM_ID']);
		static::assertSame(['2025-08-01', '2025-08-31', '08:00', '18:00'], [$data['DATE_FROM'], $data['DATE_UNTIL'], $data['TIME_FROM'], $data['TIME_UNTIL']]);
		static::assertSame('checked', $data['DATE_PERIOD_CHECKED']);
		static::assertSame('checked', $data['TIME_PERIOD_CHECKED']);
		static::assertSame('checked', $data['CONDITIONAL_PLAY_CHECKED']);
	}

	#[Group('units')]
	public function testTheWeekdaysAreBitsAndTheChosenOnesAreChecked(): void
	{
		$this->preparer->prepare(1, ['weekdays' => [1 => ['from' => 0, 'until' => 8], 4 => ['from' => 0, 'until' => 8]]]);

		$data = $this->rendered();

		static::assertSame(
			[
				['WEEKDAY_NUMBER' => 1, 'WEEKDAY' => 'Monday', 'WEEK_DAY_CHECKED' => 'checked'],
				['WEEKDAY_NUMBER' => 2, 'WEEKDAY' => 'Tuesday'],
				['WEEKDAY_NUMBER' => 4, 'WEEKDAY' => 'Wednesday', 'WEEK_DAY_CHECKED' => 'checked'],
			],
			$data['list_weekdays']
		);
		static::assertSame('checked', $data['CONDITIONAL_PLAY_CHECKED']);
		static::assertArrayNotHasKey('DATE_PERIOD_CHECKED', $data);
	}

	#[Group('units')]
	public function testOnlyTheDateIsChecked(): void
	{
		$this->preparer->prepare(1, ['date' => ['from' => '2025-08-01', 'until' => '']]);

		$data = $this->rendered();

		static::assertSame('checked', $data['DATE_PERIOD_CHECKED']);
		static::assertArrayNotHasKey('TIME_PERIOD_CHECKED', $data);
		static::assertSame('checked', $data['CONDITIONAL_PLAY_CHECKED']);
	}

	#[Group('units')]
	public function testAnItemWithoutConditionsIsNotChecked(): void
	{
		$this->preparer->prepare(1, []);

		$data = $this->rendered();

		static::assertSame(['', '', '', ''], [$data['DATE_FROM'], $data['DATE_UNTIL'], $data['TIME_FROM'], $data['TIME_UNTIL']]);
		static::assertArrayNotHasKey('DATE_PERIOD_CHECKED', $data);
		static::assertArrayNotHasKey('TIME_PERIOD_CHECKED', $data);
		static::assertArrayNotHasKey('CONDITIONAL_PLAY_CHECKED', $data);
		static::assertCount(3, $data['list_weekdays']);
		static::assertArrayNotHasKey('WEEK_DAY_CHECKED', $data['list_weekdays'][0]);
	}
}
