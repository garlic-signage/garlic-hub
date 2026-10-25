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

namespace App\Framework\Validation;

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

	/**
	 * Normalizes a string: valid UTF-8, no control characters, no surrounding whitespace.
	 *
	 * It does NOT escape. Escaping is the job of the output (template, XML writer),
	 * because only the output knows its context.
	 */
	public function string(string $value): string
	{
		$value = mb_scrub($value, 'UTF-8');
		$value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

		return trim($value);
	}

	/**
	 * Keeps only the allowed tags, and these without attributes: strip_tags() leaves the attributes of an allowed
	 * tag as they are, so onclick, style or a javascript: link would run in the page.
	 */
	public function html(string $value = ''): string
	{
		$html = strip_tags($value, $this->allowedTags);

		// an attribute value in quotes may contain a >
		return (string) preg_replace_callback(
			'/<(\/?)([a-z][a-z0-9]*)\b(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/i',
			static fn(array $tag): string => '<' . $tag[1] . strtolower($tag[2]) . ($tag[1] === '' && preg_match('~/\s*>$~', $tag[0]) === 1 ? '/' : '') . '>',
			$html
		);
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
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Non-scalar entries (nested arrays from manipulated requests) are dropped.
	 *
	 * @param array<mixed> $values
	 * @return string[]
	 */
	public function stringArray(array $values = []): array
	{
		return array_map(
			fn(mixed $s): string => $this->string((string) $s),
			array_filter($values, 'is_scalar')
		);
	}

	/**
	 * @param array<mixed> $values
	 * @return int[]
	 */
	public function intArray(array $values = []): array
	{
		return array_map(fn(mixed $i): int => (int) $i, array_filter($values, 'is_scalar'));
	}

	/**
	 * @param array<mixed> $values
	 * @return float[]
	 */
	public function floatArray(array $values = []): array
	{
		return array_map(fn(mixed $f): float => (float) $f, array_filter($values, 'is_scalar'));
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
	 * Decodes JSON and normalizes all strings (also nested ones) like string() does.
	 * Other types are kept. Does not escape.
	 *
	 * @return array<string,mixed>|list<array<string,mixed>>
	 */
	public function jsonHTML(string $jsonString): array
	{
		$data = json_decode($jsonString, true);

		if (json_last_error() !== JSON_ERROR_NONE || !is_array($data))
			return [];

		array_walk_recursive($data, function (mixed &$value): void
		{
			if (is_string($value))
				$value = $this->string($value);
		});

		return $data;
	}

}
