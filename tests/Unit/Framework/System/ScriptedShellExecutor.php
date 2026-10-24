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

namespace Tests\Unit\Framework\System;

use App\Framework\Exceptions\CoreException;
use App\Framework\System\ShellExecutor;

/**
 * Starts no process: answers every known command with the given output and fails for any other one, so a
 * wrong command shows up as a missing result.
 */
class ScriptedShellExecutor extends ShellExecutor
{
	/** @var list<string> */
	public array $commands = [];

	private string $command = '';

	/**
	 * @param array<string,string> $outputs command => output
	 */
	public function __construct(public array $outputs = [])
	{
		parent::__construct();
	}

	public function setCommand(string $command): static
	{
		$this->command = $command;
		return $this;
	}

	public function executeSimple(): string
	{
		$this->commands[] = $this->command;
		if (!array_key_exists($this->command, $this->outputs))
			throw new CoreException('Unexpected command: ' . $this->command);

		return $this->outputs[$this->command];
	}
}
