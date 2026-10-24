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

use App\Framework\Http\Session;
use App\Framework\Localization\Locales\SessionLocaleExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class SessionLocaleExtractorTest extends TestCase
{
	private const array WHITE_LIST = ['en_US', 'de_DE', 'fr_FR'];

	private Session&Stub $sessionStub;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionStub = $this->createStub(Session::class);
	}

	#[Group('units')]
	public function testExtractLocaleFromSession(): void
	{
		$this->sessionStub->method('get')->willReturnMap([['locale', 'de_DE']]);

		static::assertSame('de_DE', $this->extractor()->extractLocale(self::WHITE_LIST));
	}

	/**
	 * @param string|array<string,mixed>|null $sessionValue
	 */
	#[Group('units')]
	#[DataProvider('invalidLocaleProvider')]
	public function testExtractLocaleFallsBackToTheDefault(string|array|null $sessionValue): void
	{
		$this->sessionStub->method('get')->willReturnMap([['locale', $sessionValue]]);

		static::assertSame('en_US', $this->extractor()->extractLocale(self::WHITE_LIST));
	}

	/**
	 * @return array<string, array{string|array<string,mixed>|null}>
	 */
	public static function invalidLocaleProvider(): array
	{
		return [
			'nothing in the session'  => [null],
			'locale not allowed'      => ['es_ES'],
			'empty string'            => [''],
			'wrong case'              => ['de_de'],
			'array instead of string' => [['locale' => 'es_ES']],
			'array with an allowed value' => [['locale' => 'de_DE']],
		];
	}

	#[Group('units')]
	public function testTheDefaultLocaleIsReturnedEvenIfItIsNotInTheWhiteList(): void
	{
		$this->sessionStub->method('get')->willReturn(null);

		static::assertSame('en', (new SessionLocaleExtractor($this->sessionStub))->extractLocale(self::WHITE_LIST));
	}

	private function extractor(): SessionLocaleExtractor
	{
		return new SessionLocaleExtractor($this->sessionStub, 'en_US');
	}
}
