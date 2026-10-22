<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\System;

use App\Framework\Exceptions\CoreException;

/**
 * Class ShellExecute
 */
class ShellExecutor
{
	private string $command = '';

	public function __construct() {}

	public function setCommand(string $command): static
	{
		$this->command = $command;
		return $this;
	}

	/**
	 * @return array{output: array<string>, code: int}
	 * @throws CoreException
	 */
	public function execute(): array
	{
		$this->checkCommand();

		$output = [];
		$returnCode = 0;
		exec($this->command . ' 2>&1', $output, $returnCode);

		if ($returnCode !== 0)
			throw new CoreException("Command failed: $this->command \n". 'output: '. implode("\n", $output));

		return ['output' => $output, 'code' => $returnCode];
	}

	/**
	 * @throws CoreException
	 */
	public function executeSimple(): string
	{
		$this->checkCommand();
		$response = shell_exec($this->command . ' 2>&1');
		if ($response === false || $response === null)
			throw new CoreException("Command failed: $this->command");

		return $response;
	}

	/**
	 * @throws CoreException
	 */
	private function checkCommand(): void
	{
		if (empty($this->command))
			throw new CoreException('No command set for execution.');
	}

}