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

use App\Framework\Core\Xml\XmlParsingException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class XmlParsingExceptionTest extends TestCase
{
	#[Group('units')]
	public function testConstructorHandlesEmptyLibXmlErrors(): void
	{
		$exception = new XmlParsingException('An error occurred', []);

		static::assertSame('An error occurred', $exception->getMessage());
		static::assertSame([], $exception->getFormattedErrors());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testConstructorFormatsLibXmlErrors(): void
	{
		$libXmlStub = $this->createStub(\LibXMLError::class);
		$libXmlStub->level = 2;
		$libXmlStub->code = 100;
		$libXmlStub->message = "Invalid tag";
		$libXmlStub->file = "test.xml";
		$libXmlStub->line = 5;
		$libXmlStub->column = 10;

		$exception = new XmlParsingException('An error occurred', [$libXmlStub]);

		$expectedErrors = [[
			'level' => 2,
			'code' => 100,
			'message' => 'Invalid tag',
			'file' => 'test.xml',
			'line' => 5,
			'column' => 10,
		]];

		static::assertSame($expectedErrors, $exception->getFormattedErrors());
	}
}
