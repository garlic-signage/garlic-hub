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
use App\Modules\Playlists\Helper\Trigger\TemplatePreparer;
use App\Modules\Playlists\Helper\Trigger\TriggerPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingTemplateAdapter;

class TemplatePreparerTest extends TestCase
{
	#[Group('units')]
	public function testTheTriggerFormIsRenderedForTheItem(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn([1 => 'Monday', 2 => 'Tuesday']);
		$template = new RecordingTemplateAdapter();
		$preparer = new TemplatePreparer($template, new TriggerPreparer($translator));

		$preparer->prepare(12);

		static::assertSame('rendered playlists/trigger', $preparer->render());
		static::assertSame('playlists/trigger', $template->rendered[0]['template']);
		static::assertSame(12, $template->rendered[0]['data']['TRIGGER_ITEM_ID']);
		static::assertSame('playlists.wallclock', $template->rendered[0]['data']['LANG_WALLCLOCK']);
		static::assertSame([['WEEKDAY_NUMBER' => 1, 'LANG_WEEKDAY_NAME' => 'Monday'], ['WEEKDAY_NUMBER' => 2, 'LANG_WEEKDAY_NAME' => 'Tuesday']], $template->rendered[0]['data']['wallclock_weekdays']);
	}
}
