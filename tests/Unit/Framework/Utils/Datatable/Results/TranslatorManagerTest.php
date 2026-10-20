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


namespace Tests\Unit\Framework\Utils\Datatable\Results;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TranslatorManagerTest extends TestCase
{
	private Translator&MockObject $translatorMock;
	private TranslatorManager $translatorManager;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock = $this->createMock(Translator::class);
		$this->translatorManager = new TranslatorManager($this->translatorMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testTranslateShouldReturnEmptyStringWhenTranslationIsSkipped(): void
	{
		$headerField = $this->createMock(HeaderField::class);
		$headerField->method('shouldSkipTranslation')->willReturn(true);

		$result = $this->translatorManager->translate($headerField);

		static::assertSame('', $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testTranslateShouldUseSpecificLangModuleWhenProvided(): void
	{
		$headerField = $this->createMock(HeaderField::class);
		$headerField->method('shouldSkipTranslation')->willReturn(false);
		$headerField->method('hasSpecificLangModule')->willReturn(true);
		$headerField->method('getSpecificLanguageModule')->willReturn('specific_lang_module');
		$headerField->method('getName')->willReturn('key');

		$this->translatorMock->expects($this->once())
			->method('translate')
			->with('key', 'specific_lang_module')
			->willReturn('translated_value');

		$result = $this->translatorManager->translate($headerField);

		static::assertSame('translated_value', $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testTranslateShouldIterateOverLanguageModulesAndReturnFirstTranslatedValue(): void
	{
		$headerField = $this->createMock(HeaderField::class);
		$headerField->method('shouldSkipTranslation')->willReturn(false);
		$headerField->method('hasSpecificLangModule')->willReturn(false);
		$headerField->method('getName')->willReturn('key');

		$this->translatorManager->addLanguageModule('module_1')->addLanguageModule('module_2');

		$this->translatorMock->method('translate')->willReturnMap([
			['key', 'module_1', [], ''],
			['key', 'module_2', [], 'translated_value']
		]);

		$result = $this->translatorManager->translate($headerField);

		static::assertSame('translated_value', $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testTranslateShouldReturnEmptyStringWhenNoTranslationIsFound(): void
	{
		$headerField = $this->createMock(HeaderField::class);
		$headerField->method('shouldSkipTranslation')->willReturn(false);
		$headerField->method('hasSpecificLangModule')->willReturn(false);
		$headerField->method('getName')->willReturn('key');

		$this->translatorManager->addLanguageModule('module_1')->addLanguageModule('module_2');

		$this->translatorMock->method('translate')->willReturn('');

		$result = $this->translatorManager->translate($headerField);

		static::assertSame('', $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testTranslateShouldHandleFrameworkExceptionGracefully(): void
	{
		$headerField = $this->createMock(HeaderField::class);
		$headerField->method('shouldSkipTranslation')->willReturn(false);
		$headerField->method('hasSpecificLangModule')->willReturn(false);
		$headerField->method('getName')->willReturn('key');

		$this->translatorManager->addLanguageModule('module_1')->addLanguageModule('module_2');

		$this->translatorMock->method('translate')->will($this->throwException(new FrameworkException('error')));

		$result = $this->translatorManager->translate($headerField);

		static::assertSame('', $result);
	}
}