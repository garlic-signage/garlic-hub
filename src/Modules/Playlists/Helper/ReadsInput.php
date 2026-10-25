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

namespace App\Modules\Playlists\Helper;

/**
 * Reads the input of a request for the orchestrators. The input comes from the outside (a form, JSON, the
 * address) and has any shape: a value which is not of the expected kind counts as missing, so a request
 * with `playlist_id[]=1` gets the answer for a missing playlist and is not taken for the playlist 1.
 */
trait ReadsInput
{
	/** @var array<string,mixed> */
	private array $input = [];

	/**
	 * @param array<string,mixed> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;

		return $this;
	}

	/**
	 * The text of a field, '' when it is missing or no text.
	 */
	private function text(string $key): string
	{
		$value = $this->input[$key] ?? '';

		return is_string($value) ? $value : '';
	}

	/**
	 * Text or number as text: an id of a media is text, the id of a playlist or template is a number in JSON ("3" in a form).
	 * '' when it is missing or something else.
	 */
	private function scalarText(string $key): string
	{
		$value = $this->input[$key] ?? '';

		return is_int($value) ? (string) $value : (is_string($value) ? $value : '');
	}

	/**
	 * A number from JSON (5) or from a form or an address ("5"). Everything else is no id.
	 */
	private function hasId(string $key): bool
	{
		return $this->isId($this->input[$key] ?? null);
	}

	/**
	 * The id of a field, 0 when it is missing or no id.
	 */
	private function id(string $key): int
	{
		return $this->hasId($key) ? (int) $this->input[$key] : 0;
	}

	/**
	 * A list of ids, null when the field is missing, no list or contains something else than ids.
	 *
	 * @return list<int>|null
	 */
	private function idList(string $key): ?array
	{
		$value = $this->input[$key] ?? null;
		if (!is_array($value))
			return null;

		$ids = [];
		foreach ($value as $id)
		{
			if (!$this->isId($id))
				return null;

			$ids[] = (int) $id;
		}

		return $ids;
	}

	/**
	 * A number (also as text) which is not negative, null when the field is missing or no such number.
	 */
	private function number(string $key): ?float
	{
		$value = $this->input[$key] ?? null;

		return (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) && $value >= 0 ? (float) $value : null;
	}

	private function isId(mixed $value): bool
	{
		return is_int($value) ? $value >= 0 : (is_string($value) && ctype_digit($value));
	}
}
