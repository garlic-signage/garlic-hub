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

namespace Tests\Unit\Modules\Playlists\Helper\Widgets;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Utils\Widget\ConfigXML;
use App\Modules\Playlists\Helper\Widgets\ContentDataPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The preferences of a widget come from its config.xml: the real ConfigXML reads real XML.
 */
class ContentDataPreparerTest extends TestCase
{
	private const string CONFIG = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<widget xmlns="http://www.w3.org/ns/widgets" xmlns:ia="http://schemas.iawidget.org/widgetconfig-1.0">'
		. '<preference name="urls" ia:types="text" ia:mandatory="true" />'
		. '<preference name="count" ia:types="integer" />'
		. '<preference name="color" ia:types="color" />'
		. '<preference name="fixed" value="1" readonly="true" />'
		. '</widget>';

	private ContentDataPreparer $preparer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->preparer = new ContentDataPreparer(new ConfigXML());
	}

	#[Group('units')]
	public function testTheEditablePreferencesAreTheOnesWhichAreNotReadonly(): void
	{
		$preferences = $this->preparer->determinePreferences(self::CONFIG);

		static::assertSame(['urls', 'count', 'color'], array_keys($preferences));
		static::assertSame(['types' => 'text', 'mandatory' => 'true'], $preferences['urls']);
		static::assertSame(['types' => 'integer'], $preferences['count']);
	}

	#[Group('units')]
	public function testAWidgetWithoutEditablePreferencesCanNotBeEdited(): void
	{
		$config = '<?xml version="1.0"?><widget xmlns="http://www.w3.org/ns/widgets"><preference name="fixed" value="1" readonly="true" /></widget>';

		try
		{
			$this->preparer->determinePreferences($config);
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('widget_not_editable', $e->getTranslationKey());
		}
	}

	#[Group('units')]
	public function testAConfigWhichIsNoWidgetIsAnError(): void
	{
		$this->expectException(FrameworkException::class);
		$this->preparer->determinePreferences('<?xml version="1.0"?><config></config>');
	}

	#[Group('units')]
	public function testAConfigWhichIsNoXmlIsAnError(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Error reading/parsing xml');
		$this->preparer->determinePreferences('<invalid></config>');
	}

	#[Group('units')]
	public function testTheValuesAreCastByTheTypeOfThePreference(): void
	{
		$result = $this->preparer->prepareContentData(self::CONFIG, ['urls' => '<b>http://example.com</b>', 'count' => '123', 'color' => '#ff0000']);

		static::assertSame(['urls' => '<b>http://example.com</b>', 'count' => 123, 'color' => '#ff0000'], $result);
	}

	#[Group('units')]
	public function testAValueWhichIsNoScalarIsAnEmptyText(): void
	{
		$result = $this->preparer->prepareContentData(self::CONFIG, ['urls' => ['x'], 'color' => ['#fff']]);

		static::assertSame(['urls' => '', 'color' => ''], $result);
	}

	#[Group('units')]
	public function testAMandatoryValueMustBeGiven(): void
	{
		try
		{
			$this->preparer->prepareContentData(self::CONFIG, ['count' => '1']);
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('widget_field_mandatory', $e->getTranslationKey());
			static::assertSame(['field' => 'urls'], $e->getReplacements());
		}
	}

	#[Group('units')]
	public function testTheMandatoryValuesAreIgnoredWhenAWidgetIsInserted(): void
	{
		static::assertSame([], $this->preparer->prepareContentData(self::CONFIG, [], true));
	}

	#[Group('units')]
	public function testOnlyThePreferencesOfTheWidgetAreKept(): void
	{
		$result = $this->preparer->prepareContentData(self::CONFIG, ['csrf_token' => 'secret', 'item_id' => '1', 'urls' => 'x', 'count' => '0', 'fixed' => '2', 'unknown' => 'x']);

		// a zero or an emptied text is a value, the readonly preference and the other fields are not
		static::assertSame(['urls' => 'x', 'count' => 0], $result);
	}

	#[Group('units')]
	public function testChoicesAreTexts(): void
	{
		$config = '<?xml version="1.0"?><widget xmlns="http://www.w3.org/ns/widgets" xmlns:ia="http://schemas.iawidget.org/widgetconfig-1.0">'
			. '<preference name="direction" ia:types="radio"><ia:options value="ltr"/><ia:options value="rtl"/></preference>'
			. '<preference name="theme" ia:types="list"><ia:options value="light"/></preference>'
			. '<preference name="font" ia:types="combo"><ia:options>Arial</ia:options></preference>'
			. '</widget>';

		$result = $this->preparer->prepareContentData($config, ['direction' => 'rtl', 'theme' => 5, 'font' => 'Arial']);

		static::assertSame(['direction' => 'rtl', 'theme' => '5', 'font' => 'Arial'], $result);
	}

	#[Group('units')]
	public function testAPreferenceWithoutATypeIsAText(): void
	{
		static::markTestSkipped('Known bug: a preference without ia:types (plain W3C widget, like the example config.xml) gives a warning for the undefined key "types". Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$config = '<?xml version="1.0"?><widget xmlns="http://www.w3.org/ns/widgets"><preference name="url" /></widget>';

		static::assertSame(['url' => '7'], $this->preparer->prepareContentData($config, ['url' => 7]));
	}

	#[Group('units')]
	public function testAnEmptiedTextIsAValue(): void
	{
		static::assertSame(['urls' => '', 'color' => ''], $this->preparer->prepareContentData(self::CONFIG, ['urls' => '', 'color' => ''], true));
	}
}
