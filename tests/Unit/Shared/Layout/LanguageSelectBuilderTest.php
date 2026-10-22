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

namespace Tests\Unit\Shared\Layout;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\Translator;
use App\Shared\Layout\LanguageSelectBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class LanguageSelectBuilderTest extends TestCase
{
	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildListsOnlyTranslatedLocales(): void
	{
		$translatorStub = $this->createStub(Translator::class);
		$translatorStub->method('translateArrayForOptions')->willReturn(['de_DE' => 'Deutsch', 'en_US' => 'English']);
		$localesStub = $this->createStub(Locales::class);
		$localesStub->method('getAvailableLocales')->willReturn(['de_DE', 'xx_XX', 'en_US']);

		$builder = new LanguageSelectBuilder($translatorStub, $localesStub);

		static::assertSame([
			['LOCALE_LONG' => 'de_DE', 'LOCALE_SMALL' => 'de', 'LANGUAGE_NAME' => 'Deutsch'],
			['LOCALE_LONG' => 'en_US', 'LOCALE_SMALL' => 'en', 'LANGUAGE_NAME' => 'English']
		], $builder->build());
	}
}
