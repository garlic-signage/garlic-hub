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

namespace App\Framework\Controller;

/**
 * The input of a request (a form, JSON, the address). It comes from the outside and has any shape: a value which is
 * not of the expected kind counts as missing, so a request with `playlist_id[]=1` gets the answer for a missing
 * playlist and is not taken for the playlist 1, and no value causes an error of the server.
 */
final readonly class RequestInput
{
	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct(private array $values = [])
	{
	}

	/**
	 * What the parser gave for the body: an array, or nothing when no body was sent or it could not be read.
	 */
	public static function of(mixed $parsedBody): self
	{
		/** @var array<string,mixed> $values */
		$values = is_array($parsedBody) ? $parsedBody : [];

		return new self($values);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all(): array
	{
		return $this->values;
	}

	/**
	 * The text of a field, '' when it is missing or no text.
	 */
	public function text(string $key): string
	{
		$value = $this->values[$key] ?? '';

		return is_string($value) ? $value : '';
	}

	/**
	 * Text or number as text: an id of a media is text, the id of a playlist or template is a number in JSON ("3" in a form).
	 * '' when it is missing or something else.
	 */
	public function scalarText(string $key): string
	{
		$value = $this->values[$key] ?? '';

		return is_int($value) ? (string) $value : (is_string($value) ? $value : '');
	}

	/**
	 * A number from JSON (5) or from a form or an address ("5"). Everything else is no id.
	 */
	public function hasId(string $key): bool
	{
		return self::isId($this->values[$key] ?? null);
	}

	/**
	 * The id of a field, 0 when it is missing or no id.
	 */
	public function id(string $key): int
	{
		return $this->hasId($key) ? (int) $this->values[$key] : 0;
	}

	/**
	 * A number or null when the field is missing or no number.
	 */
	public function optionalInt(string $key): ?int
	{
		return $this->hasId($key) ? $this->id($key) : null;
	}

	/**
	 * A list of ids, null when the field is missing, no list or contains something else than ids.
	 *
	 * @return list<int>|null
	 */
	public function idList(string $key): ?array
	{
		$value = $this->values[$key] ?? null;
		if (!is_array($value))
			return null;

		$ids = [];
		foreach ($value as $id)
		{
			if (!self::isId($id))
				return null;

			$ids[] = (int) $id;
		}

		return $ids;
	}

	/**
	 * A number (also as text) which is not negative, null when the field is missing or no such number.
	 */
	public function number(string $key): ?float
	{
		$value = $this->values[$key] ?? null;

		return (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) && $value >= 0 ? (float) $value : null;
	}

	private static function isId(mixed $value): bool
	{
		return is_int($value) ? $value >= 0 : (is_string($value) && ctype_digit($value));
	}
}
