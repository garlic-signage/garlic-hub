<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
 * The update of garlic-hub from the web page, on installations with garlic-hub-update (the Raspberry Pi image,
 * APP_SELF_UPDATE=true in its .env).
 *
 * PHP gets no root rights: it only writes the empty file var/update/requested. A systemd path unit of the
 * image sees it and starts garlic-hub-update as root, which takes the latest release, nothing of PHP. The script
 * deletes the request at its start and writes its progress to var/update/status.json.
 */
readonly class SelfUpdate
{
	public const string REQUEST_FILE = 'requested';
	public const string STATUS_FILE  = 'status.json';

	/** the states of status.json */
	public const array STATES = ['running', 'done', 'current', 'failed'];

	public function __construct(private bool $enabled, private string $updateDir)
	{}

	public function isAvailable(): bool
	{
		return $this->enabled && is_dir($this->updateDir);
	}

	/**
	 * @throws CoreException
	 */
	public function request(): void
	{
		if (!$this->isAvailable())
			throw new CoreException('The update is not available on this installation.');

		if (@file_put_contents($this->updateDir . '/' . self::REQUEST_FILE, '') === false)
			throw new CoreException('The update could not be requested: ' . $this->updateDir . ' is not writable.');
	}

	/**
	 * Requested, but the script has not started yet.
	 */
	public function isRequested(): bool
	{
		return is_file($this->updateDir . '/' . self::REQUEST_FILE);
	}

	/**
	 * The last run of garlic-hub-update, null before the first one or when the file is broken.
	 *
	 * @return array{state:string, step:string, from:string, to:string, time:string}|null
	 */
	public function readStatus(): ?array
	{
		$file = $this->updateDir . '/' . self::STATUS_FILE;
		if (!is_file($file))
			return null;

		$status = json_decode((string) file_get_contents($file), true);
		if (!is_array($status) || !in_array($status['state'] ?? null, self::STATES, true))
			return null;

		return [
			'state' => (string) $status['state'],
			'step'  => is_string($status['step'] ?? null) ? $status['step'] : '',
			'from'  => is_string($status['from'] ?? null) ? $status['from'] : '',
			'to'    => is_string($status['to'] ?? null) ? $status['to'] : '',
			'time'  => is_string($status['time'] ?? null) ? $status['time'] : '',
		];
	}
}
