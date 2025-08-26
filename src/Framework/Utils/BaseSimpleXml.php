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

namespace App\Framework\Utils;

use App\Framework\Exceptions\ModuleException;
use LibXMLError;
use SimpleXMLElement;

/**
 * This is just a little helper for using SimpleXMl in php
 * In fact, it just provides a wrapper for the php function
 * to deal with libxml errors.
 *
 * It throws an exception if simplexml_load_string() (or maybe simplexml_load_file())
 * fails due to XMl parsing errors
 * Additionally it provides a public method to retrieve the libxml error
 *
 * So, if your class is using PHP's SimpleXML class, just extend from this, to use the two methods.
 *
 * we need this to throw correct exceptions
 *
 */
abstract class BaseSimpleXml
{
	/** @var LibXMLError[] */
	protected array $xmlErrors;

	protected SimpleXMLElement $xmlObj;

	abstract protected function getModuleName(): string;

	public function getXmlObj(): SimpleXMLElement
	{
		return $this->xmlObj;
	}

	public function setXmlObj(SimpleXMLElement $xmlObj): void
	{
		$this->xmlObj = $xmlObj;
	}

	/**
	 * @return LibXMLError[]
	 */
	protected function getXmlErrors(): array
	{
		return $this->xmlErrors;
	}

	/**
	 * @param list<LibXMLError> $xmlErrors
	 */
	protected function setXmlErrors(array $xmlErrors): void
	{
		$this->xmlErrors = $xmlErrors;
	}

	/**
	 * @throws  ModuleException
	 */
	protected function loadXmlFromString(string $xml_input): static
	{
		libxml_use_internal_errors(true);

		// clear possible previously stored errors
		$this->xmlErrors = [];
		libxml_clear_errors();

		$simple_xml = simplexml_load_string($xml_input);

		if ($simple_xml === false)
		{
			throw new ModuleException($this->getModuleName(), 'Error reading/parsing xml');
		}
		$this->setXmlObj($simple_xml);
		return $this;
	}

	/**
	 * @throws  ModuleException
	 */
	protected function loadXMlFromFile(string $file_name): static
	{
		libxml_use_internal_errors(true);

		// clear possible previously stored errors
		$this->xmlErrors = [];
		libxml_clear_errors();

		$simple_xml = simplexml_load_file($file_name);

		if ($simple_xml === false)
			throw new ModuleException($this->getModuleName(), 'Error reading/parsing xml');

		$this->setXmlObj($simple_xml);
		return $this;
	}

	/**
	 * @return LibXMLError[]
	 */
	public function getXmlErrorArray(): array
	{
		$this->buildXmlErrors();
		return $this->getXmlErrors();
	}

	public function getXmlErrorsAsString(): string
	{
		$this->buildXmlErrors();

		if (array_key_exists(0, $this->xmlErrors))
		{
			$last_error = $this->xmlErrors[0];
			/* @var $last_error LibXMLError */

			return sprintf('%s: %s Line: %s, Column: %s',
				$this->getNamedErrorLevel($last_error->level),
				trim($last_error->message),
				$last_error->line,
				$last_error->column);
		}

		return '';
	}

	/**
	 * reads the internal libxml_error api and returns
	 * the errors as a string (concatenated by line breaks)
	 *
	 * since this clears the libxml error, it should not
	 * be called from outside
	 */
	private function buildXmlErrors(): void
	{
		$ar_error_lines = [];

		foreach(libxml_get_errors() as $error)
		{
			$ar_error_lines[] = $error;
		}

		$this->setXmlErrors($ar_error_lines);
	}

	private function getNamedErrorLevel(int $error_level): string
	{
		return match ($error_level)
		{
			LIBXML_ERR_WARNING => 'Warning',
			LIBXML_ERR_ERROR => 'Recoverable Error',
			LIBXML_ERR_FATAL => 'Fatal Error',
			default => 'Unknown level',
		};
	}
}