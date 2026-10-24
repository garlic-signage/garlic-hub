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

namespace Tests\Unit\Framework\Localization\Translate;

use App\Framework\Exceptions\CoreException;
use App\Framework\Localization\Translate\IniTranslationLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class IniTranslationLoaderTest extends TestCase
{
	private string $baseDirectory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->baseDirectory = getenv('TEST_BASE_DIR') . '/resources/translations_tests/';
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadForValidFile(): void
	{
		$loader = new IniTranslationLoader($this->baseDirectory);

		static::assertSame(['username' => 'Username', 'email' => 'E-Mail'], $loader->load('en', 'valid'));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadUsesTheFileOfTheLanguage(): void
	{
		$loader = new IniTranslationLoader($this->baseDirectory);

		static::assertSame('Hallo, {name}!', $loader->load('de', 'greeting')['greeting']);
		static::assertArrayNotHasKey('farewell', $loader->load('de', 'greeting'));
	}

	/**
	 * A language which has no file for the module (or is not translated at all) gets the English one.
	 *
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadFallsBackToEnglishWhenTheLanguageHasNoFile(): void
	{
		$loader = new IniTranslationLoader($this->baseDirectory);

		static::assertSame(['username' => 'Username', 'email' => 'E-Mail'], $loader->load('de', 'valid'));
		static::assertSame('Goodbye', $loader->load('xx', 'greeting')['farewell']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadKeepsSectionsAndRawValues(): void
	{
		$loader = new IniTranslationLoader($this->baseDirectory);

		static::assertSame(
			[
				'save'          => 'Save',
				'options'       => ['opt1' => 'Option 1', 'opt2' => 'Option 2'],
				'time_unit_ago' => ['days' => '{count, plural, one {1 day ago} other {# days ago}}'],
			],
			$loader->load('en', 'sections')
		);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadWorksWithAndWithoutTrailingSlashInPath(): void
	{
		$withoutSlash = new IniTranslationLoader(rtrim($this->baseDirectory, '/'));

		static::assertSame(['username' => 'Username', 'email' => 'E-Mail'], $withoutSlash->load('en', 'valid'));
	}

	#[Group('units')]
	public function testLoadThrowsExceptionForNonExistentFile(): void
	{
		$loader = new IniTranslationLoader($this->baseDirectory);

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Translation file not found: ' . $this->baseDirectory . 'en/nonexistent.ini');

		$loader->load('de', 'nonexistent');
	}

	#[Group('units')]
	public function testLoadThrowsExceptionForInvalidIniFile(): void
	{
		$loader = new IniTranslationLoader($this->baseDirectory);

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Invalid INI file format: ' . $this->baseDirectory . 'en/invalid.ini');

		$loader->load('en', 'invalid');
	}
}
