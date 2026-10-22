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
use App\Framework\Xml\XmlReader;
use App\Framework\Xml\XmlReaderFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class XmlReaderFactoryTest extends TestCase
{
	private XmlReaderFactory $xmlReaderFactory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->xmlReaderFactory = new XmlReaderFactory();
	}

	#[Group('units')]
	public function testCreateFromSimpleXmlObjectValidInput(): void
	{
		$simpleXmlObj = new \SimpleXMLElement('<root><node>value</node></root>');
		$xmlReader = $this->xmlReaderFactory->createFromSimpleXmlObject($simpleXmlObj);

		// use equals here because Same is like ===
		static::assertEquals(new XmlReader($simpleXmlObj), $xmlReader);
	}

	#[Group('units')]
	public function testCreateFromStringValidInput(): void
	{
		$validXml = '<root><node>value</node></root>';
		$xmlReader = $this->xmlReaderFactory->createFromString($validXml);

		static::assertEquals(new XmlReader(new \SimpleXMLElement($validXml)), $xmlReader);
	}

	#[Group('units')]
	public function testCreateFromStringInvalidInput(): void
	{
		$this->expectException(XmlParsingException::class);

		$invalidXml = '<root><node>value</node>'; // Missing closing root tag
		$this->xmlReaderFactory->createFromString($invalidXml);
	}

	#[Group('units')]
	public function testCreateFromFileValidInput(): void
	{
		$mockFilePath = __DIR__ . '/mock_valid.xml'; // Assuming the file exists with valid XML content
		file_put_contents($mockFilePath, '<root><node>value</node></root>');

		try
		{
			$xmlReader = $this->xmlReaderFactory->createFromFile($mockFilePath);
			static::assertEquals(new XmlReader(new \SimpleXMLElement('<root><node>value</node></root>')), $xmlReader);
		}
		finally
		{
			unlink($mockFilePath);
		}
	}

	#[Group('units')]
	public function testCreateFromFileInvalidFilePath(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("File '/invalid/path/to/file.xml' not found or not readable.");

		$this->xmlReaderFactory->createFromFile('/invalid/path/to/file.xml');
	}

	#[Group('units')]
	public function testCreateFromFileInvalidXml(): void
	{
		$mockFilePath = __DIR__ . '/mock_invalid.xml'; // Assuming the file exists with invalid XML content
		file_put_contents($mockFilePath, '<root><node>value</node>'); // Missing closing root tag

		try
		{
			$this->expectException(XmlParsingException::class);
			$this->xmlReaderFactory->createFromFile($mockFilePath);
		}
		finally
		{
			unlink($mockFilePath); // Cleanup the temporary file
		}
	}
}
