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

namespace Tests\Unit\Framework\Utils\Widget;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Widget\ConfigXML;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The config.xml of a widget (W3C and the IAdea extension) with the real parser and the real configs of two widgets.
 */
class ConfigXMLTest extends TestCase
{
	private const string W3C = 'xmlns="http://www.w3.org/ns/widgets"';
	private const string IA  = 'xmlns:ia="http://schemas.iawidget.org/widgetconfig-1.0"';

	private string $baseDirectory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->baseDirectory = getenv('TEST_BASE_DIR') . '/resources/widgets';
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	private function fromFile(string $name): ConfigXML
	{
		$xml = file_get_contents($this->baseDirectory . '/' . $name);
		static::assertIsString($xml, 'The fixture could not be read.');

		return (new ConfigXML())->load($xml);
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	private function fromString(string $content): ConfigXML
	{
		return (new ConfigXML())->load('<?xml version="1.0" encoding="UTF-8"?><widget ' . self::W3C . ' ' . self::IA . '>' . $content . '</widget>');
	}

	// ---------------------------------------------------------------- load

	#[Group('units')]
	public function testAnEmptyStringThrows(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Error reading/parsing xml');

		(new ConfigXML())->load('');
	}

	#[Group('units')]
	public function testXmlWhichIsNoWidgetThrowsWithoutThePartOfTheDocument(): void
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?><not_widget><secret>PRIVATE CONTENT</secret></not_widget>';

		try
		{
			(new ConfigXML())->load($xml);
		}
		catch (FrameworkException $e)
		{
			static::assertSame('This string is not a config.xml for widgets (root element: not_widget).', $e->getMessage());
			static::assertStringNotContainsString('PRIVATE CONTENT', $e->getMessage());

			return;
		}

		static::fail('Expected a FrameworkException.');
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testLoadReturnsTheObject(): void
	{
		$config = new ConfigXML();

		static::assertSame($config, $config->load('<widget ' . self::W3C . '/>'));
	}

	// ---------------------------------------------------------------- basic

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDefaultsForAnEmptyWidget(): void
	{
		$config = (new ConfigXML())->load('<?xml version="1.0" encoding="UTF-8"?><widget></widget>')->parseBasic();

		static::assertSame('', $config->getId());
		static::assertSame('', $config->getVersion());
		static::assertSame('', $config->getNameByLanguage());
		static::assertSame('', $config->getDescriptionByLanguage());
		static::assertSame('', $config->getLicenseByLanguage());
		static::assertSame('icon.png', $config->getIcon());
		static::assertSame('index.html', $config->getContent());
		static::assertSame('en', $config->getDefaultLanguage());
		static::assertSame('ltr', $config->getDefaultDirection());
		static::assertSame([], $config->getName());
		static::assertSame([], $config->getAuthor());
	}

	#[Group('units')]
	public function testParseBasicWithoutXmlChangesNothing(): void
	{
		$config = (new ConfigXML())->parseBasic();

		static::assertSame('', $config->getId());
		static::assertSame('icon.png', $config->getIcon());
		static::assertSame('en', $config->getDefaultLanguage());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheStandardConfigIsParsed(): void
	{
		$config = $this->fromFile('standard_config.xml')->parseBasic();

		static::assertSame('http://example.org/exampleWidget', $config->getId());
		static::assertSame('2.0', $config->getVersion());
		static::assertSame('start.html', $config->getContent());
		static::assertSame('icon.jpg', $config->getIcon());
		static::assertSame('en', $config->getDefaultLanguage());
		static::assertSame('ltr', $config->getDefaultDirection());

		static::assertSame(['en' => 'The example Widget!', 'de' => 'Ein Beispiel Widget!'], $config->getName());
		static::assertSame(['en' => 'English description.', 'de' => 'Deutsche Beschreibung.'], $config->getDescription());
		static::assertSame(['en' => 'English license', 'de' => 'Deutsche Lizenz'], $config->getLicense());
		static::assertSame('Deutsche Beschreibung.', $config->getDescriptionByLanguage('de'));
		static::assertSame('Deutsche Lizenz', $config->getLicenseByLanguage('de'));
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheIAdeaConfigIsParsed(): void
	{
		$config = $this->fromFile('iadea_config.xml')->parseBasic();

		static::assertSame('4679064D-0C36-4FCB-97EE-F9A00F8D77C2', $config->getId());
		static::assertSame('1.0.0.0', $config->getVersion());
		static::assertSame('index.html', $config->getContent());
		static::assertSame('icon.png', $config->getIcon());
		static::assertSame(['en' => 'Rss'], $config->getName());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheIdAndTheVersionAreChildrenOrAttributes(): void
	{
		$attributes = $this->fromString('')->parseBasic();
		static::assertSame('', $attributes->getId());

		$w3c = (new ConfigXML())->load('<widget ' . self::W3C . ' id="http://example.org/w" version="2.0 Beta"/>')->parseBasic();
		static::assertSame('http://example.org/w', $w3c->getId());
		static::assertSame('2.0 Beta', $w3c->getVersion());

		$iadea = $this->fromString('<id>{123456-7890-IAdea}</id><version>2.0.0.0</version>')->parseBasic();
		static::assertSame('123456-7890-IAdea', $iadea->getId(), 'The braces of the IAdea id are removed.');
		static::assertSame('2.0.0.0', $iadea->getVersion());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('languageProvider')]
	public function testTheNameInTheLanguageOfTheRequest(string $requested, string $expected): void
	{
		$config = $this->fromString('<name xml:lang="de" short="Nicht so">Nicht so, mein Freund!</name><name xml:lang="el" short="Όχι">Όχι έτσι, βρε φίλε μου!</name>')->parseBasic();

		static::assertSame($expected, $config->getNameByLanguage($requested));
	}

	/**
	 * Only the first two letters count, a language which is not there gets the default, and this is the
	 * first language of the file when there is no English one.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function languageProvider(): array
	{
		return [
			'default'                => ['en', 'Nicht so, mein Freund!'],
			'german'                 => ['de', 'Nicht so, mein Freund!'],
			'german with country'    => ['de-De', 'Nicht so, mein Freund!'],
			'upper case'             => ['DE', 'Nicht so, mein Freund!'],
			'greek'                  => ['el', 'Όχι έτσι, βρε φίλε μου!'],
			'long text starting el'  => ['el_should_check only_first_letters', 'Όχι έτσι, βρε φίλε μου!'],
			'language not there'     => ['fr', 'Nicht so, mein Freund!'],
			'empty'                  => ['', 'Nicht so, mein Freund!'],
		];
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheFirstLanguageBecomesTheEnglishOne(): void
	{
		$config = $this->fromString('<name xml:lang="de">Nicht so</name><name xml:lang="el">Όχι</name>')->parseBasic();

		static::assertSame(['de' => 'Nicht so', 'el' => 'Όχι', 'en' => 'Nicht so'], $config->getName());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAnElementWithoutALanguageIsEnglishAndTheTextIsTrimmed(): void
	{
		$config = $this->fromString("<name>\n   The Widget   \n</name>")->parseBasic();

		static::assertSame(['en' => 'The Widget'], $config->getName());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheDefaultLanguageOfTheWidget(): void
	{
		$config = (new ConfigXML())->load('<widget ' . self::W3C . ' xml:lang="de-DE"/>')->parseBasic();

		static::assertSame('de', $config->getDefaultLanguage());
	}

	/**
	 * The direction is the direction, it must not replace the language.
	 *
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheDefaultDirection(): void
	{
		$config = (new ConfigXML())->load('<widget ' . self::W3C . ' dir="rtl" xml:lang="fa"><name short="برنامه">برنامه</name></widget>')->parseBasic();

		static::assertSame('rtl', $config->getDefaultDirection());
		static::assertSame('fa', $config->getDefaultLanguage());
		static::assertSame(['en' => 'برنامه'], $config->getName());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheAuthorHasTextsOnly(): void
	{
		$config = $this->fromFile('standard_config.xml')->parseBasic();

		static::assertSame(
			['href' => 'http://foo-bar.example.org/', 'email' => 'foo-bar@example.org', 'data' => 'Foo Bar Corp'],
			$config->getAuthor()
		);
		static::assertSame(
			'{"href":"http:\\/\\/foo-bar.example.org\\/","email":"foo-bar@example.org","data":"Foo Bar Corp"}',
			json_encode($config->getAuthor())
		);
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheAuthorMayHaveOnlyAName(): void
	{
		$config = $this->fromString('<author>Foo Bar Corp</author>')->parseBasic();

		static::assertSame(['data' => 'Foo Bar Corp'], $config->getAuthor());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testIconAndContentAreTakenFromTheFirstElement(): void
	{
		$config = $this->fromString('<icon src="icons/a.png"/><icon src="icons/b.png"/><content src="start.html"/>')->parseBasic();

		static::assertSame('icons/a.png', $config->getIcon());
		static::assertSame('start.html', $config->getContent());
	}

	// ---------------------------------------------------------------- preferences

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheStandardPreferencesWithoutReadonlyOnes(): void
	{
		$config = $this->fromFile('standard_config.xml')->parseBasic()->parsePreferences();

		static::assertSame(['url' => []], $config->getPreferences());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testThePreferencesOfTheIAdeaConfig(): void
	{
		$preferences = $this->fromFile('iadea_config.xml')->parseBasic()->parsePreferences()->getPreferences();

		static::assertSame(
			['urls', 'WritingDirection', 'showSweepHand', 'BackgroundColor', 'BackgroundOpacity', 'Color', 'Title-Length', 'Content-Length', 'cities', 'align'],
			array_keys($preferences),
			'The read-only preferences (count, margin, noTransform) are left out.'
		);

		static::assertSame(['types' => 'text', 'mandatory' => 'true', 'tooltip' => 'e.g. http://rss.cnn.com/rss/cnn_topstories.rss'], $preferences['urls']);
		static::assertSame(['types' => 'radio', 'default' => 'ltr', 'options' => ['ltr' => 'ltr', 'rtl' => 'rtl']], $preferences['WritingDirection']);
		static::assertSame(['types' => 'checkbox', 'default' => 'true'], $preferences['showSweepHand']);
		static::assertSame(['types' => 'color', 'default' => '#008080', 'previewbg' => 'true'], $preferences['BackgroundColor']);
		static::assertSame(['value' => '100', 'types' => 'colorOpacity'], $preferences['BackgroundOpacity']);
		static::assertSame(['types' => 'color', 'default' => '#ffffff'], $preferences['Color']);
		static::assertSame(['types' => 'list', 'default' => 'right', 'options' => ['right' => 'right', 'center' => 'center', 'left' => 'left']], $preferences['align']);
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testNotesInSeveralLanguages(): void
	{
		$preferences = $this->fromFile('iadea_config.xml')->parseBasic()->parsePreferences()->getPreferences();

		static::assertSame(
			['value' => '', 'types' => 'integer', 'notes' => ['en' => 'Control the length of the RSS title.', 'de' => 'Steuert die Länge des RSS-Titels.']],
			$preferences['Title-Length']
		);
		static::assertSame(
			['value' => '', 'types' => 'integer', 'notes' => ['en' => 'Control the length of the RSS content. 0 or non-integer words will display the full length of the content.']],
			$preferences['Content-Length']
		);
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheOptionsOfAComboAreTheirOwnKeys(): void
	{
		$preferences = $this->fromFile('iadea_config.xml')->parseBasic()->parsePreferences()->getPreferences();

		static::assertSame('combo', $preferences['cities']['types']);
		static::assertSame('New York', $preferences['cities']['default']);
		static::assertSame(
			['Athens', 'Hamburg', 'London', 'New York', 'Beijing', 'Paris', 'Sao Paulo', 'Taipei', 'Tokyo'],
			array_keys($preferences['cities']['options'])
		);
		static::assertSame(array_keys($preferences['cities']['options']), array_values($preferences['cities']['options']));
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPreferencesWithoutAnExistingConfig(): void
	{
		static::assertSame([], (new ConfigXML())->parsePreferences()->getPreferences());
		static::assertSame([], $this->fromString('')->parsePreferences()->getPreferences());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('skippedPreferenceProvider')]
	public function testPreferencesWhichAreReadonlyOrHaveNoNameAreLeftOut(string $preference): void
	{
		static::assertSame([], $this->fromString($preference)->parsePreferences()->getPreferences());
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function skippedPreferenceProvider(): array
	{
		return [
			'readonly'            => ['<preference name="count" value="255" readonly="true"/>'],
			'readonly upper case' => ['<preference name="count" value="255" readonly="TRUE"/>'],
			'no name'             => ['<preference value="ea31ad3a23fd2f" readonly="false"/>'],
			'empty name'          => ['<preference name="" value="1"/>'],
		];
	}

	/**
	 * A radio or a list needs the type to know what its options are. A preference with options but without
	 * a type is kept without options and does not break the upload of the widget.
	 *
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testOptionsWithoutATypeAreIgnored(): void
	{
		$config = $this->fromString('<preference name="p" value="1"><ia:options resourceKey="a" value="b"/></preference>')->parsePreferences();

		static::assertSame(['p' => ['value' => '1']], $config->getPreferences());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAReadonlyFalsePreferenceIsKept(): void
	{
		$config = $this->fromString('<preference name="p" value="1" readonly="false"/>')->parsePreferences();

		static::assertSame(['p' => ['value' => '1']], $config->getPreferences());
	}

	// ---------------------------------------------------------------- editable preferences

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('editableProvider')]
	public function testHasEditablePreferences(string $preferences, bool $expected): void
	{
		static::assertSame($expected, $this->fromString($preferences)->hasEditablePreferences());
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function editableProvider(): array
	{
		return [
			'none'                    => ['', false],
			'only readonly'           => ['<preference name="count" readonly="true"/><preference name="noTransform" readonly="true"/>', false],
			'none readonly'           => ['<preference name="count" ia:types="integer"/><preference name="noTransform"/>', true],
			'one of them is editable' => ['<preference name="count" readonly="true"/><preference name="url"/>', true],
			'readonly false'          => ['<preference name="count" readonly="false"/>', true],
		];
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheIAdeaConfigHasEditablePreferences(): void
	{
		static::assertTrue($this->fromFile('iadea_config.xml')->hasEditablePreferences());
		static::assertTrue($this->fromFile('standard_config.xml')->hasEditablePreferences());
	}

	#[Group('units')]
	public function testWithoutAConfigThereAreNoEditablePreferences(): void
	{
		static::assertFalse((new ConfigXML())->hasEditablePreferences());
	}
}
