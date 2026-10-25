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
 * For the orchestrators, which get the input of a request with setInput() and read it with text(), id() and the other
 * methods of RequestInput: a value which is not of the expected kind counts as missing.
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

	private function text(string $key): string
	{
		return new RequestInput($this->input)->text($key);
	}

	private function scalarText(string $key): string
	{
		return new RequestInput($this->input)->scalarText($key);
	}

	private function hasId(string $key): bool
	{
		return new RequestInput($this->input)->hasId($key);
	}

	private function id(string $key): int
	{
		return new RequestInput($this->input)->id($key);
	}

	private function optionalInt(string $key): ?int
	{
		return new RequestInput($this->input)->optionalInt($key);
	}

	/**
	 * @return list<int>|null
	 */
	private function idList(string $key): ?array
	{
		return new RequestInput($this->input)->idList($key);
	}

	private function number(string $key): ?float
	{
		return new RequestInput($this->input)->number($key);
	}
}
