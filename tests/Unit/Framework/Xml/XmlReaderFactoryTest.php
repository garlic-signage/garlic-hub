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

namespace Tests\Unit\Framework\Xml;

use App\Framework\Xml\XmlParsingException;
use App\Framework\Xml\XmlReaderFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class XmlReaderFactoryTest extends TestCase
{
	private const string VALID = '<root id="7"><node>value</node></root>';

	private XmlReaderFactory $factory;
	/** @var list<string> */
	private array $files = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->factory = new XmlReaderFactory();
	}

	protected function tearDown(): void
	{
		array_map('unlink', $this->files);
		parent::tearDown();
	}

	private function file(string $content): string
	{
		$path = sys_get_temp_dir() . '/xml_reader_' . uniqid() . '.xml';
		file_put_contents($path, $content);
		$this->files[] = $path;

		return $path;
	}

	#[Group('units')]
	public function testCreateFromSimpleXmlObject(): void
	{
		$reader = $this->factory->createFromSimpleXmlObject(new SimpleXMLElement(self::VALID));

		static::assertSame('7', $reader->readAttribute('id'));
		static::assertSame('value', (string) $reader->readSubnode('node'));
	}

	#[Group('units')]
	public function testCreateFromString(): void
	{
		$reader = $this->factory->createFromString(self::VALID);

		static::assertSame(['id' => '7'], $reader->getAllAttributes());
		static::assertSame('value', (string) $reader->readSubnode('node'));
	}

	#[Group('units')]
	public function testCreateFromFile(): void
	{
		$reader = $this->factory->createFromFile($this->file(self::VALID));

		static::assertSame(['id' => '7'], $reader->getAllAttributes());
		static::assertSame('value', (string) $reader->readSubnode('node'));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function brokenXmlProvider(): array
	{
		return [
			'not closed'  => ['<root><node>value</node>'],
			'only text'   => ['no xml at all'],
			'two roots'   => ['<a/><b/>'],
			'empty'       => [''],
		];
	}

	#[Group('units')]
	#[DataProvider('brokenXmlProvider')]
	public function testBrokenXmlFromStringThrowsWithTheErrorsOfTheParser(string $xml): void
	{
		try
		{
			$this->factory->createFromString($xml);
		}
		catch (XmlParsingException $e)
		{
			static::assertSame('Error parsing XML string', $e->getMessage());

			return;
		}

		static::fail('Expected an XmlParsingException.');
	}

	#[Group('units')]
	public function testTheExceptionHasTheLineAndColumnOfTheError(): void
	{
		try
		{
			$this->factory->createFromString("<root>\n<node>value</node>\n<open>\n</root>");
		}
		catch (XmlParsingException $e)
		{
			$errors = $e->getFormattedErrors();
			static::assertNotSame([], $errors);
			static::assertSame(LIBXML_ERR_FATAL, $errors[0]['level']);
			static::assertSame(4, $errors[0]['line']);

			return;
		}

		static::fail('Expected an XmlParsingException.');
	}

	#[Group('units')]
	#[DataProvider('brokenXmlProvider')]
	public function testBrokenXmlFromFileThrowsAndNamesTheFile(string $xml): void
	{
		$path = $this->file($xml);

		try
		{
			$this->factory->createFromFile($path);
		}
		catch (XmlParsingException $e)
		{
			static::assertSame('Error parsing XML file: ' . $path, $e->getMessage());

			return;
		}

		static::fail('Expected an XmlParsingException.');
	}

	#[Group('units')]
	public function testAFileWhichDoesNotExistThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("File '/invalid/path/to/file.xml' not found or not readable.");

		$this->factory->createFromFile('/invalid/path/to/file.xml');
	}

	/**
	 * A directory is no file: it must not end in a notice of the XML parser.
	 */
	#[Group('units')]
	public function testADirectoryThrowsTheSameException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('not found or not readable');

		$this->factory->createFromFile(sys_get_temp_dir());
	}

	/**
	 * Parsing must not change how libxml reports errors for the rest of the application.
	 */
	#[Group('units')]
	public function testTheSettingOfLibxmlIsGivenBack(): void
	{
		$before = libxml_use_internal_errors(false);

		try
		{
			$this->factory->createFromString(self::VALID);
			$this->factory->createFromFile($this->file(self::VALID));
			try
			{
				$this->factory->createFromString('<root>');
			}
			catch (XmlParsingException)
			{
				// expected
			}

			static::assertFalse(libxml_use_internal_errors(false), 'The setting was changed.');
		}
		finally
		{
			libxml_use_internal_errors($before);
		}
	}

	/**
	 * An external entity must not read a file of the server into the document (XXE).
	 */
	#[Group('units')]
	public function testAnExternalEntityDoesNotLoadAFile(): void
	{
		$secret = $this->file('TOPSECRET');
		$xml    = '<?xml version="1.0"?><!DOCTYPE a [<!ENTITY e SYSTEM "file://' . $secret . '">]><a>&e;</a>';

		$content = '';
		try
		{
			$content = (string) $this->factory->createFromString($xml)->readSubnode('e');
			$content .= var_export($this->factory->createFromString($xml), true);
		}
		catch (XmlParsingException)
		{
			// refused is fine as well
		}

		static::assertStringNotContainsString('TOPSECRET', $content);
	}
}
