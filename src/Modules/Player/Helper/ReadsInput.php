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

namespace App\Modules\Player\Helper;

/**
 * Reads the input of a request. The input comes from the outside (a form, JSON) and has any shape: a value
 * which is not of the expected kind counts as missing, so a request with `player_id[]=x` gets the answer for
 * a missing id and is not taken for the player 1.
 */
trait ReadsInput
{
	/**
	 * What the parser gave for the body: an array, or nothing when no body was sent or it could not be read.
	 *
	 * @return array<string,mixed>
	 */
	private function bodyOf(mixed $parsedBody): array
	{
		/** @var array<string,mixed> $body */
		$body = is_array($parsedBody) ? $parsedBody : [];

		return $body;
	}

	/**
	 * @param array<string,mixed> $input
	 */
	private function textOf(array $input, string $key): string
	{
		$value = $input[$key] ?? '';

		return is_string($value) ? $value : '';
	}

	/**
	 * A number from JSON (5) or from a form (“5”). Everything else is no id.
	 *
	 * @param array<string,mixed> $input
	 */
	private function hasIdIn(array $input, string $key): bool
	{
		$value = $input[$key] ?? null;

		return is_int($value) ? $value >= 0 : (is_string($value) && ctype_digit($value));
	}

	/**
	 * The id of a field, 0 when it is missing or no id.
	 *
	 * @param array<string,mixed> $input
	 */
	private function idOf(array $input, string $key): int
	{
		return $this->hasIdIn($input, $key) ? (int) $input[$key] : 0;
	}
}
