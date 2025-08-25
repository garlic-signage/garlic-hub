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

namespace App\Framework\Core;

/**
 * A class for sanitizing various types of input data.
 */
class Sanitizer
{
	private string $allowedTags;

	public function __construct(string $allowedTags = '')
	{
		$this->allowedTags = $allowedTags;
	}

	public function string(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}

	public function html(string $value = ''): string
	{
		return strip_tags($value, $this->allowedTags);
	}

	public function int(string|int $value = ''): int
	{
		return (int) $value;
	}

	public function float(string|float $value = ''): float
	{
		return (float) $value; // Simple cast for sanitization
	}

	public function bool(string|bool $value = ''): bool
	{
		return (bool) $value;
	}

	/**
	 * @param string[] $values
	 * @return string[]
	 */
	public function stringArray(array $values = []): array
	{
		return array_map(function (string $s){
			return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
		}, $values);
	}

	/**
	 * @param int[] $values
	 * @return int[]
	 */
	public function intArray(array $values = []): array
	{
		return array_map(function (int $i) {
			return $i;
		}, $values);
	}

	/**
	 * @param float[] $values
	 * @return float[]
	 */	public function floatArray(array $values = []): array
	{
		return array_map(function (float $f) {
			return $f;
		}, $values);
	}

	/**
	 * @return array<string,mixed>|list<array<string,mixed>>
	 */
	public function jsonArray(string $jsonString): array
	{
		$data = json_decode($jsonString, true);

		if (json_last_error() !== JSON_ERROR_NONE || !is_array($data))
			return [];

		return $data;
	}

	/**
	 * @return array<string,mixed>|list<array<string,mixed>>
	 */
	public function jsonHTML(string $jsonString): array
	{
		$data = json_decode($jsonString, true);

		if (json_last_error() !== JSON_ERROR_NONE || !is_array($data))
			return [];

		return array_map(function (string $s){
			return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
		}, $data);
	}

}