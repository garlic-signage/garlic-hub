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

namespace Tests\Support;

use App\Framework\System\ShellExecutor;

/**
 * Does not start a process: remembers the commands, answers ffprobe with a fixed JSON
 * and, like ffmpeg, writes the thumbnail when asked to.
 */
class RecordingShellExecutor extends ShellExecutor
{
	/** @var list<string> */
	public array $commands = [];
	public string $probeOutput = '';
	/** thumbnail file which "ffmpeg" creates, empty when it should create none */
	public string $createsFile = '';

	private string $command = '';

	public function setCommand(string $command): static
	{
		$this->command = $command;
		return $this;
	}

	public function execute(): array
	{
		$this->commands[] = $this->command;
		if ($this->createsFile !== '')
			file_put_contents($this->createsFile, 'jpg');

		return ['output' => [], 'code' => 0];
	}

	public function executeSimple(): string
	{
		$this->commands[] = $this->command;

		return $this->probeOutput;
	}
}
