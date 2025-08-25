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


namespace App\Framework\Core\Xml;

use App\Framework\Exceptions\BaseException;
use LibXMLError;
use Throwable;

/**
 * Exception thrown when an error occurs during XML parsing.
 */
class XmlParsingException extends BaseException
{
	/**
	 * @var array<array<string,int|string>>
	 */
	private array $formattedErrors;

	/**
	 * @param LibXMLError[] $libXmlErrors
	 */
	public function __construct(string $message, array $libXmlErrors = [], int $code = 0, ?Throwable $previous = null)
	{
		$this->setModuleName('Xml');
		parent::__construct($message, $code, $previous);

		$this->formattedErrors = array_map(function(LibXMLError $error) {
			return [
				'level' => $error->level,    // 1: XML_ERR_WARNING, 2: XML_ERR_ERROR, 3: XML_ERR_FATAL
				'code' => $error->code,
				'message' => trim($error->message),
				'file' => $error->file,
				'line' => $error->line,
				'column' => $error->column,
			];
		}, $libXmlErrors);
	}

	/**
	 * @return array<array<string,int|string>>
	 */
	public function getFormattedErrors(): array
	{
		return $this->formattedErrors;
	}

}