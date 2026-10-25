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

namespace Tests\Unit\Framework\Utils;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\BaseSimpleXml;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class ConcreteBaseSimpleXml extends BaseSimpleXml
{
	/**
	 * @throws ModuleException
	 */
	public function loadXmlFromStringPublic(string $xmlString): static
	{
		return $this->loadXmlFromString($xmlString);
	}

	/**
	 * @throws ModuleException
	 */
	public function loadXmlFromFilePublic(string $filePath): static
	{
		return $this->loadXmlFromFile($filePath);
	}

	protected function getModuleName(): string
	{
		return 'TestModule';
	}
}

/**
 * The wrapper around SimpleXML with the real libxml: a file which is no XML throws a ModuleException and
 * the errors of the parser can be read afterwards.
 */
class BaseSimpleXmlTest extends TestCase
{
	private const string VALID = '<?xml version="1.0"?><document><title>Something testable</title><content><![CDATA[Some "raw" <data>]]></content></document>';

	private ConcreteBaseSimpleXml $xml;
	/** @var list<string> */
	private array $files = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->xml = new ConcreteBaseSimpleXml();
	}

	protected function tearDown(): void
	{
		array_map('unlink', $this->files);
		parent::tearDown();
	}

	private function file(string $content): string
	{
		$path = sys_get_temp_dir() . '/simple_xml_' . uniqid() . '.xml';
		file_put_contents($path, $content);
		$this->files[] = $path;

		return $path;
	}

	#[Group('units')]
	public function testSetAndGetXmlObj(): void
	{
		$xmlObj = new SimpleXMLElement('<root><child>value</child></root>');

		$this->xml->setXmlObj($xmlObj);

		static::assertSame($xmlObj, $this->xml->getXmlObj());
	}

	#[Group('units')]
	public function testTheObjectIsNotThereBeforeSomethingIsLoaded(): void
	{
		$this->expectException(Error::class);

		$this->xml->getXmlObj();
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testLoadXmlFromString(): void
	{
		$result = $this->xml->loadXmlFromStringPublic(self::VALID);

		static::assertSame($this->xml, $result);
		static::assertSame('Something testable', (string) $this->xml->getXmlObj()->title);
		static::assertSame('Some "raw" <data>', (string) $this->xml->getXmlObj()->content);
		static::assertSame([], $this->xml->getXmlErrorArray());
		static::assertSame('', $this->xml->getXmlErrorsAsString());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testLoadXmlFromFile(): void
	{
		$result = $this->xml->loadXmlFromFilePublic($this->file(self::VALID));

		static::assertSame($this->xml, $result);
		static::assertSame('Something testable', (string) $this->xml->getXmlObj()->title);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function brokenXmlProvider(): array
	{
		return [
			'not closed'     => ['<root><child>value</child>'],
			'closing tag mismatch' => ['<document><body><inner>x</inner></document>'],
			'wrong CDATA'    => ['<document><content><foo><[CDATA[x]]></foo></content></document>'],
			'empty'          => [''],
			'only text'      => ['no xml at all'],
			'two roots'      => ['<a/><b/>'],
			'attribute without quotes' => ['<a b=c/>'],
		];
	}

	#[Group('units')]
	#[DataProvider('brokenXmlProvider')]
	public function testBrokenXmlFromStringThrowsAModuleException(string $xml): void
	{
		try
		{
			$this->xml->loadXmlFromStringPublic($xml);
		}
		catch (ModuleException $e)
		{
			static::assertSame('Error reading/parsing xml', $e->getMessage());
			static::assertSame('TestModule', $e->getModuleName());

			return;
		}

		static::fail('Expected a ModuleException.');
	}

	#[Group('units')]
	#[DataProvider('brokenXmlProvider')]
	public function testBrokenXmlFromFileThrowsAModuleException(string $xml): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Error reading/parsing xml');

		$this->xml->loadXmlFromFilePublic($this->file($xml));
	}

	#[Group('units')]
	public function testAMissingFileThrowsAModuleException(): void
	{
		$this->expectException(ModuleException::class);

		$this->xml->loadXmlFromFilePublic(sys_get_temp_dir() . '/does_not_exist_' . uniqid() . '.xml');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheErrorsOfTheParserCanBeReadAfterwards(): void
	{
		try
		{
			$this->xml->loadXmlFromStringPublic("<?xml version='1.0'?>\n<document>\n <body>\n  <inner>x</inner>\n </document>");
		}
		catch (ModuleException)
		{
			// the errors are read below
		}

		$errors = $this->xml->getXmlErrorArray();

		static::assertNotSame([], $errors);
		foreach ($errors as $error)
		{
			static::assertSame(LIBXML_ERR_FATAL, $error->level);
		}
		static::assertStringContainsString('mismatch', $errors[0]->message);
	}

	/**
	 * The text names the level, the message of the parser and the place in the document. The text of the
	 * message is the one of the installed libxml, so only its form is fixed.
	 */
	#[Group('units')]
	public function testTheErrorsAsString(): void
	{
		try
		{
			$this->xml->loadXmlFromStringPublic("<?xml version='1.0'?>\n<document>\n <content><foo><[CDATA[x]]></foo></content>\n</document>");
		}
		catch (ModuleException)
		{
			// the errors are read below
		}

		static::assertMatchesRegularExpression('/^Fatal Error: .+ Line: 3, Column: \d+$/', $this->xml->getXmlErrorsAsString());
	}

	/**
	 * The errors of the parser are reset with every load, a good document has none.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheErrorsAreClearedByTheNextLoad(): void
	{
		try
		{
			$this->xml->loadXmlFromStringPublic('<root>');
		}
		catch (ModuleException)
		{
			static::assertNotSame([], $this->xml->getXmlErrorArray());
		}

		$this->xml->loadXmlFromStringPublic(self::VALID);

		static::assertSame([], $this->xml->getXmlErrorArray());
		static::assertSame('', $this->xml->getXmlErrorsAsString());
	}

	/**
	 * An external entity must not read a file of the server into the document (XXE).
	 */
	#[Group('units')]
	public function testAnExternalEntityDoesNotLoadAFile(): void
	{
		$secret = $this->file('TOPSECRET');
		$xml    = '<?xml version="1.0"?><!DOCTYPE a [<!ENTITY e SYSTEM "file://' . $secret . '">]><a>&e;</a>';

		try
		{
			$this->xml->loadXmlFromStringPublic($xml);
			$content = (string) $this->xml->getXmlObj();
		}
		catch (ModuleException)
		{
			$content = '';
		}

		static::assertStringNotContainsString('TOPSECRET', $content);
	}

	#[Group('units')]
	public function testAnExternalEntityInAFileDoesNotLoadAFileEither(): void
	{
		$secret = $this->file('TOPSECRET');
		$path   = $this->file('<?xml version="1.0"?><!DOCTYPE a [<!ENTITY e SYSTEM "file://' . $secret . '">]><a>&e;</a>');

		try
		{
			$this->xml->loadXmlFromFilePublic($path);
			$content = (string) $this->xml->getXmlObj();
		}
		catch (ModuleException)
		{
			$content = '';
		}

		static::assertStringNotContainsString('TOPSECRET', $content);
	}

	/**
	 * Entities which expand into each other (billion laughs) are refused by the parser.
	 */
	#[Group('units')]
	public function testEntitiesWhichExpandIntoEachOtherAreRefused(): void
	{
		$xml  = '<?xml version="1.0"?><!DOCTYPE l [<!ENTITY a "lol">';
		$prev = 'a';
		for ($i = 1; $i <= 9; $i++)
		{
			$name = 'e' . $i;
			$xml .= '<!ENTITY ' . $name . ' "' . str_repeat('&' . $prev . ';', 10) . '">';
			$prev = $name;
		}
		$xml .= ']><l>&' . $prev . ';</l>';

		$this->expectException(ModuleException::class);

		$this->xml->loadXmlFromStringPublic($xml);
	}
}
