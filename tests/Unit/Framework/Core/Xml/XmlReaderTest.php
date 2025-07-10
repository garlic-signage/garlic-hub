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


namespace Tests\Unit\Framework\Core\Xml;

use App\Framework\Core\Xml\XmlReader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class XmlReaderTest extends TestCase
{
	private XmlReader $xmlReader;


	#[Group('units')]
	public function testGetAllAttributesReturnsAttributesAsArray(): void
	{
		$xmlObj = new \SimpleXMLElement('<node attr1="value1" attr2="value2"/>');
		$this->xmlReader = new XmlReader($xmlObj);

		$result = $this->xmlReader->getAllAttributes();

		self::assertSame(['attr1' => 'value1', 'attr2' => 'value2'], $result);
	}

	#[Group('units')]
	public function testGetAllAttributesReturnsEmptyArrayWhenNoAttributes(): void
	{
		$xmlObj = new \SimpleXMLElement('<test>hurz</test>');
		$this->xmlReader = new XmlReader($xmlObj);

		$result = $this->xmlReader->getAllAttributes();

		self::assertSame([], $result);
	}

	#[Group('units')]
	public function testReadSubnodeReturnsCorrectSubnode(): void
	{
		$xmlObj = new \SimpleXMLElement('<root><child>content</child></root>');
		$this->xmlReader = new XmlReader($xmlObj);

		$result = $this->xmlReader->readSubnode('child');

		self::assertInstanceOf(\SimpleXMLElement::class, $result);
		self::assertSame('content', (string)$result);
	}

	#[Group('units')]
	public function testReadSubnodeReturnsNullWhenNodeDoesNotExist(): void
	{
		$xmlObj = new \SimpleXMLElement('<root><child>content</child></root>');
		$this->xmlReader = new XmlReader($xmlObj);

		$result = $this->xmlReader->readSubnode('nonexistent');

		self::assertNull($result);
	}

	#[Group('units')]
	public function testReadAttributeReturnsCorrectAttributeValue(): void
	{
		$xmlObj = new \SimpleXMLElement('<node attr="value"/>');
		$this->xmlReader = new XmlReader($xmlObj);

		$result = $this->xmlReader->readAttribute('attr');

		self::assertSame('value', $result);
	}

	#[Group('units')]
	public function testReadAttributeReturnsNullWhenAttributeDoesNotExist(): void
	{
		$xmlObj = new \SimpleXMLElement('<node/>');
		$this->xmlReader = new XmlReader($xmlObj);

		$result = $this->xmlReader->readAttribute('missingAttr');

		self::assertNull($result);
	}
}
