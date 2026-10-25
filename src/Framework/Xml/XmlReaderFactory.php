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

namespace App\Framework\Xml;

use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Factory class for creating instances of XmlReader from various XML data sources.
 *
 * This class provides static methods to construct XmlReader objects
 * from SimpleXMLElement objects, XML strings, or XML files. It ensures
 * proper error handling and validation during the conversion process.
 */
class XmlReaderFactory
{

	public function createFromSimpleXmlObject(SimpleXMLElement $simpleXmlObj): XmlReader
	{
		return new XmlReader($simpleXmlObj);
	}

	/**
	 * @param string $xmlInput
	 * @return XmlReader
	 * @throws XmlParsingException
	 */
	public function createFromString(string $xmlInput): XmlReader
	{
		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors(); // Immer vor dem Parsen aufrufen

		try
		{
			$simpleXml = simplexml_load_string($xmlInput);
			if ($simpleXml === false)
				throw new XmlParsingException('Error parsing XML string', libxml_get_errors());
		}
		finally
		{
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}

		return new XmlReader($simpleXml);
	}

	/**
	 * @param string $fileName
	 * @return XmlReader
	 * @throws XmlParsingException
	 */
	public function createFromFile(string $fileName): XmlReader
	{
		// is_file(): a directory exists and is readable, but it is no XML document
		if (!is_file($fileName) || !is_readable($fileName))
			throw new InvalidArgumentException("File '$fileName' not found or not readable.");

		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();

		try
		{
			$simpleXml = simplexml_load_file($fileName);
			if ($simpleXml === false)
				throw new XmlParsingException('Error parsing XML file: ' . $fileName, libxml_get_errors());
		}
		finally
		{
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}

		return new XmlReader($simpleXml);
	}
}
