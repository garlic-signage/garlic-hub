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

use SimpleXMLElement;

/**
 * This is just a little wrapper using SimpleXMl.
 */
class XmlReader
{
	private SimpleXMLElement $xmlObj;

	public function __construct(SimpleXMLElement $xmlObj)
	{
		$this->xmlObj = $xmlObj;
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 */
	public function getAllAttributes(): array
	{
		$attributes = [];
		$attr = $this->xmlObj->attributes();

		if ($attr === null || count($attr) === 0)
			return [];

		foreach ($attr as $key => $value)
		{
			$attributes[$key] = (string) $value;
		}

		return $attributes;
	}

	public function readSubnode(string $nodeName): ?SimpleXMLElement
	{
		if (isset($this->xmlObj->{$nodeName}))
			return $this->xmlObj->{$nodeName};

		return null;
	}

	public function readAttribute(string $attributeName): ?string
	{
		if (isset($this->xmlObj[$attributeName]))
			return (string) $this->xmlObj[$attributeName];

		return null;
	}
}