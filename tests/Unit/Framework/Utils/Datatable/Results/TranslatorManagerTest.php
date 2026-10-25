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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * The texts of the header come from the language modules in the given order. The Translator itself needs locales
 * and files, here it knows a few texts: key and module give the text, a key without text gives an empty one.
 */
class TranslatorManagerTest extends TestCase
{
	/** @var array<string,string> "key@module" => text */
	private array $texts = [
		'name@playlists'   => 'Name of the list',
		'name@main'        => 'Name',
		'created@main'     => 'Created',
		'owner@users'      => 'Owner',
	];

	/** @var array<string,string> "key@module" => message of the exception */
	private array $failing = [];

	private Translator&Stub $translatorStub;
	private TranslatorManager $manager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->translatorStub = $this->createStub(Translator::class);
		$this->translatorStub->method('translate')->willReturnCallback(
			function (string $key, string $module): string
			{
				$id = $key . '@' . $module;
				if (isset($this->failing[$id]))
					throw new FrameworkException($this->failing[$id]);

				return $this->texts[$id] ?? '';
			}
		);

		$this->manager = new TranslatorManager($this->translatorStub);
	}

	private function field(string $name): HeaderField
	{
		return (new HeaderField())->setName($name);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testASkippedFieldHasNoText(): void
	{
		$this->manager->addLanguageModule('main');

		static::assertSame('', $this->manager->translate($this->field('name')->skipTranslation(true)));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testASpecificModuleIsUsedAloneAndBeforeTheOthers(): void
	{
		$this->manager->addLanguageModule('main');

		$field = $this->field('owner')->useSpecificLangModule('users');

		static::assertSame('Owner', $this->manager->translate($field));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testASpecificModuleDoesNotFallBackToTheOthers(): void
	{
		$this->manager->addLanguageModule('main');

		$field = $this->field('created')->useSpecificLangModule('users');

		static::assertSame('', $this->manager->translate($field));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheFirstModuleWithATextWins(): void
	{
		$this->manager->addLanguageModule('playlists')->addLanguageModule('main');

		static::assertSame('Name of the list', $this->manager->translate($this->field('name')));
		static::assertSame('Created', $this->manager->translate($this->field('created')), 'A module without the key hands over to the next one.');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheOrderOfTheModulesDecides(): void
	{
		$this->manager->addLanguageModule('main')->addLanguageModule('playlists');

		static::assertSame('Name', $this->manager->translate($this->field('name')));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testWithoutATextInAnyModuleTheResultIsEmpty(): void
	{
		$this->manager->addLanguageModule('playlists')->addLanguageModule('main');

		static::assertSame('', $this->manager->translate($this->field('unknown_key')));
	}

	#[Group('units')]
	public function testWithoutModulesTheResultIsEmpty(): void
	{
		static::assertSame('', $this->manager->translate($this->field('name')));
	}

	/**
	 * A module which fails with a FrameworkException (e.g. a locale which is not configured) is skipped.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAModuleWhichFailsWithAFrameworkExceptionIsSkipped(): void
	{
		$this->failing['name@playlists'] = 'error';
		$this->manager->addLanguageModule('playlists')->addLanguageModule('main');

		static::assertSame('Name', $this->manager->translate($this->field('name')));
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAFailingSpecificModuleIsNotHidden(): void
	{
		$this->failing['name@users'] = 'error';

		$this->expectException(FrameworkException::class);

		$this->manager->translate($this->field('name')->useSpecificLangModule('users'));
	}

	/**
	 * A missing language file is a CoreException of the module, the search goes on with the next module.
	 *
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAModuleWithoutALanguageFileIsSkipped(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(
			static fn(string $key, string $module): string => $module === 'playlists' ? throw new CoreException('Translation file not found') : 'Name'
		);
		$manager = new TranslatorManager($translator);
		$manager->addLanguageModule('playlists')->addLanguageModule('main');

		static::assertSame('Name', $manager->translate($this->field('name')));
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testWhenEveryModuleFailsTheResultIsEmpty(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willThrowException(new CoreException('Translation file not found'));
		$manager = new TranslatorManager($translator);
		$manager->addLanguageModule('playlists')->addLanguageModule('main');

		static::assertSame('', $manager->translate($this->field('name')));
	}

	/**
	 * A specific module is asked on purpose: its missing file is not hidden.
	 *
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAMissingFileOfASpecificModuleIsNotHidden(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willThrowException(new CoreException('Translation file not found'));
		$manager = new TranslatorManager($translator);

		$this->expectException(CoreException::class);

		$manager->translate($this->field('name')->useSpecificLangModule('users'));
	}

	#[Group('units')]
	public function testAddLanguageModuleIsChainable(): void
	{
		static::assertSame($this->manager, $this->manager->addLanguageModule('main'));
	}
}
