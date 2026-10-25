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


namespace Tests\Unit\Modules\Playlists\Helper\Trigger;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Playlists\Helper\Trigger\TriggerPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TriggerPreparerTest extends TestCase
{
	private Translator&Stub $translatorStub;
	private TriggerPreparer $triggerPreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorStub = $this->createStub(Translator::class);
		$this->triggerPreparer = new TriggerPreparer($this->translatorStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGenerateValid(): void
	{
		$itemId = 42;
		$translationsMap = [
			['wallclock', 'playlists', 'translated_wallclock'],
			['add_wallclock', 'playlists', 'translated_add_wallclock'],
			['remove_wallclock', 'playlists', 'translated_remove_wallclock'],
			['after', 'playlists', 'translated_after'],
			['before', 'playlists', 'translated_before'],
			['begin_datetime', 'playlists', 'translated_begin_datetime'],
			['repeats', 'playlists', 'translated_repeats'],
			['no_repeats', 'playlists', 'translated_no_repeats'],
			['infinite', 'playlists', 'translated_infinite_repeats'],
			['number_repeats', 'playlists', 'translated_number_repeats'],
			['every', 'playlists', 'translated_every'],
			['minutes', 'main', 'translated_minutes'],
			['hours', 'main', 'translated_hours'],
			['days', 'main', 'translated_days'],
			['weeks', 'main', 'translated_weeks'],
			['months', 'main', 'translated_months'],
			['years', 'main', 'translated_years'],
			['accesskey', 'playlists', 'translated_accesskey'],
			['add_accesskey', 'playlists', 'translated_add_accesskey'],
			['remove_accesskey', 'playlists', 'translated_remove_accesskey'],
			['touch', 'playlists', 'translated_touch'],
			['add_touch', 'playlists', 'translated_add_touch'],
			['remove_touch', 'playlists', 'translated_remove_touch'],
			['notify', 'playlists', 'translated_notify'],
			['add_notify', 'playlists', 'translated_add_notify'],
			['remove_notify', 'playlists', 'translated_remove_notify'],
			['only_alphanumeric', 'playlists', 'translated_only_alphanumeric'],
		];

		$this->translatorStub
			->method('translate')
			->willReturnMap($translationsMap);

		$this->translatorStub
			->method('translateArrayForOptions')
			->willReturn([
				1 => 'Monday',
				2 => 'Tuesday',
			]);

		$this->triggerPreparer->generate($itemId);

		$expectedTemplateData = [
			'TRIGGER_ITEM_ID' => $itemId,
			'LANG_WALLCLOCK' => 'translated_wallclock',
			'LANG_ADD_WALLCLOCK' => 'translated_add_wallclock',
			'LANG_REMOVE_WALLCLOCK' => 'translated_remove_wallclock',
			'wallclock_weekdays' => [
				['WEEKDAY_NUMBER' => 1, 'LANG_WEEKDAY_NAME' => 'Monday'],
				['WEEKDAY_NUMBER' => 2, 'LANG_WEEKDAY_NAME' => 'Tuesday'],
			],
			'LANG_WEEKDAY_AFTER' => 'translated_after',
			'LANG_WEEKDAY_BEFORE' => 'translated_before',
			'LANG_DATETIME' => 'translated_begin_datetime',
			'LANG_REPEATS' => 'translated_repeats',
			'LANG_NO_REPEATS' => 'translated_no_repeats',
			'LANG_INFINITE_REPEATS' => 'translated_infinite_repeats',
			'LANG_NUMBER_REPEATS' => 'translated_number_repeats',
			'LANG_EVERY' => 'translated_every',
			'LANG_REPEAT_MINUTES' => 'translated_minutes',
			'LANG_REPEAT_HOURS' => 'translated_hours',
			'LANG_REPEAT_DAYS' => 'translated_days',
			'LANG_REPEAT_WEEKS' => 'translated_weeks',
			'LANG_REPEAT_MONTHS' => 'translated_months',
			'LANG_REPEAT_YEARS' => 'translated_years',
			'LANG_ACCESSKEY' => 'translated_accesskey',
			'LANG_ADD_ACCESSKEY' => 'translated_add_accesskey',
			'LANG_REMOVE_ACCESSKEY' => 'translated_remove_accesskey',
			'LANG_TOUCH' => 'translated_touch',
			'LANG_ADD_TOUCH' => 'translated_add_touch',
			'LANG_REMOVE_TOUCH' => 'translated_remove_touch',
			'LANG_NOTIFY' => 'translated_notify',
			'LANG_ADD_NOTIFY' => 'translated_add_notify',
			'LANG_REMOVE_NOTIFY' => 'translated_remove_notify',
			'LANG_ONLY_ALPHANUMERIC' => 'translated_only_alphanumeric',
		];

		self::assertSame($expectedTemplateData, $this->triggerPreparer->getTemplateData());
	}
}
