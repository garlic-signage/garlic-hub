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
use Exception;
use LibXMLError;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class XmlParsingExceptionTest extends TestCase
{
	/**
	 * @return LibXMLError[] the errors the parser really gives for the document
	 */
	private function libXmlErrors(string $xml): array
	{
		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();
		simplexml_load_string($xml);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		return $errors;
	}

	#[Group('units')]
	public function testConstructorHandlesEmptyLibXmlErrors(): void
	{
		$exception = new XmlParsingException('An error occurred', []);

		static::assertSame('An error occurred', $exception->getMessage());
		static::assertSame([], $exception->getFormattedErrors());
	}

	#[Group('units')]
	public function testTheExceptionBelongsToTheXmlModule(): void
	{
		$previous  = new Exception('cause');
		$exception = new XmlParsingException('An error occurred', [], 42, $previous);

		static::assertSame('Xml', $exception->getModuleName());
		static::assertSame(42, $exception->getCode());
		static::assertSame($previous, $exception->getPrevious());
	}

	#[Group('units')]
	public function testConstructorFormatsTheRealErrorsOfTheParser(): void
	{
		$errors = $this->libXmlErrors("<root>\n  <node>value</node>\n  <open>\n</root>");
		static::assertNotSame([], $errors, 'The parser must report errors for this document.');

		$formatted = (new XmlParsingException('An error occurred', $errors))->getFormattedErrors();

		static::assertCount(count($errors), $formatted);
		static::assertSame(
			['level', 'code', 'message', 'file', 'line', 'column'],
			array_keys($formatted[0])
		);
		static::assertSame(LIBXML_ERR_FATAL, $formatted[0]['level']);
		static::assertStringContainsString('mismatch', (string) $formatted[0]['message']);
		static::assertSame(4, $formatted[0]['line']);
		static::assertSame($errors[0]->code, $formatted[0]['code']);
	}

	#[Group('units')]
	public function testTheMessageOfAnErrorIsTrimmed(): void
	{
		$errors = $this->libXmlErrors('<root>');

		$formatted = (new XmlParsingException('x', $errors))->getFormattedErrors();

		static::assertSame(trim($errors[0]->message), $formatted[0]['message']);
		static::assertStringEndsNotWith("\n", $formatted[0]['message']);
	}
}
