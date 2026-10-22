<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Localization\Locales;

use App\Framework\Localization\Locales\LocaleExtractorInterface;
use App\Framework\Localization\Locales\SessionLocaleExtractor;
use App\Framework\Http\Session;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SessionLocaleExtractorTest extends TestCase
{
	private Session&MockObject $sessionMock;
	private LocaleExtractorInterface $localeExtractor;

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionMock = $this->createMock(Session::class);
		$this->localeExtractor = new SessionLocaleExtractor($this->sessionMock, 'en_US');
	}

	#[Group('units')]
	public function testExtractLocaleFromSession(): void
	{
		$this->sessionMock->method('get')->with('locale')->willReturn('de_DE');

		$whiteList = ['en_US', 'de_DE', 'fr_FR'];
		$result = $this->localeExtractor->extractLocale($whiteList);

		static::assertEquals('de_DE', $result);
	}

	#[Group('units')]
	public function testExtractLocaleFallbackToDefault(): void
	{
		$this->sessionMock->method('get')->with('locale')->willReturn(null);

		$whiteList = ['en_US', 'de_DE', 'fr_FR'];
		$result = $this->localeExtractor->extractLocale($whiteList);

		static::assertEquals('en_US', $result);
	}

	#[Group('units')]
	public function testExtractLocaleFallbackToDefaultForInvalidLocale(): void
	{
		$this->sessionMock->method('get')->with('locale')->willReturn(['locale' => 'es_ES']);

		$whiteList = ['en_US', 'de_DE', 'fr_FR'];
		$result = $this->localeExtractor->extractLocale($whiteList);

		static::assertEquals('en_US', $result);
	}
}