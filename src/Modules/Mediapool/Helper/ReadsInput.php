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

namespace App\Modules\Mediapool\Helper;

/**
 * Reads the input of a request for the orchestrators. The input comes from the outside (a form, JSON, the
 * address) and has any shape: a value which is not of the expected kind counts as missing, so a request
 * with `name[]=x` gets the answer for a missing name and no error of the server.
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
	 * A number from JSON (5) or from a form or an address ("5"). Everything else is no id.
	 */
	private function hasId(string $key): bool
	{
		$value = $this->input[$key] ?? null;

		return is_int($value) ? $value >= 0 : (is_string($value) && ctype_digit($value));
	}

	/**
	 * The id of a field, 0 when it is missing or no id.
	 */
	private function id(string $key): int
	{
		if (!$this->hasId($key))
			return 0;

		return (int) $this->input[$key];
	}

	/**
	 * A number or null when the field is missing or no number.
	 */
	private function optionalInt(string $key): ?int
	{
		return $this->hasId($key) ? $this->id($key) : null;
	}
}
