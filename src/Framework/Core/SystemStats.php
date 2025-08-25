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

namespace App\Framework\Core;

use App\Framework\Exceptions\CoreException;

/**
 * The SystemStats class provides functionality to collect and manage
 * system statistics such as RAM usage, disk usage, and system load.
 * It works primarily on Linux-based systems and extracts data
 * using shell commands executed via a ShellExecutor.
 */
class SystemStats
{
	private ShellExecutor $shellExecutor;

	/** @var array{total: int, used: int, free: int} */
	private array $ramStats = ['total' => 0, 'used'  => 0, 'free'  => 0,];
	/** @var array{size: string, used: string, available: string, percent: string} */
	private array $discInfo = ['size' => '', 'used' => '', 'available' => '', 'percent' => ''];
	/** @var array{'1_min': string, '5_min': string, '15_min': string} */
	private array $loadData = ['1_min' => '', '5_min' => '', '15_min' => ''];
	private bool $isLinux = false;

	public function __construct(ShellExecutor $shellExecutor)
	{
		$this->shellExecutor = $shellExecutor;

		if (strcasecmp(PHP_OS, 'Linux') == 0)
			$this->isLinux = true;
	}

	public function isLinux(): bool
	{
		return $this->isLinux;
	}

	public function setIsLinux(bool $is): void
	{
		$this->isLinux = $is;
	}

	/**
	 * @return array{size: string, used: string, available: string, percent: string}
	 */
	public function getDiscInfo(): array
	{
		return $this->discInfo;
	}

	/**
	 * @return array{total: int, used: int, free: int}
	 */
	public function getRamStats(): array
	{
		return $this->ramStats;
	}

	/**
	 * @return array{'1_min': string, '5_min': string, '15_min': string}
	 */
	public function getLoadData(): array
	{
		return $this->loadData;
	}

	/**
	 * @throws CoreException
	 */
	public function determineSystemStats(): void
	{
		if (!$this->isLinux)
			return;

		$this->determineRamStats();
		$this->determineDiskUsage();
		$this->determineSystemLoad();
	}

	/**
	 * @throws CoreException
	 */
	public function determineRamStats(): void
	{
		$free    = $this->shellExecutor->setCommand('free -m')->executeSimple();
		$freeArr = explode("\n", $free);
		if (!isset($freeArr[1]))
			return;

		$mem = explode(' ', $freeArr[1]);
		$mem = array_filter($mem); // Remove empty values
		$mem = array_merge($mem); // Reindex array

		$this->ramStats = [
			'total' => (int) $mem[1],
			'used'  => (int) $mem[2],
			'free'  => (int) $mem[3]
		];
	}

	public function determineSystemLoad(): void
	{
		$load = sys_getloadavg();
		if ($load === false || count($load) < 3)
			return;

		$this->loadData =  [
			'1_min' => number_format($load[0], 2, '.', ''),
			'5_min' => number_format($load[1], 2, '.', ''),
			'15_min' => number_format($load[2], 2, '.', '')
		];
	}

	/**
	 * @throws CoreException
	 */
	public function determineDiskUsage(): void
	{
		$output = $this->shellExecutor->setCommand('df -h --total')->executeSimple();

		$lines = explode("\n", $output);
		foreach ($lines as $line)
		{
			$line = trim($line);

			if (!str_starts_with($line, 'total'))
				continue;

			$parts = preg_split('/\s+/', $line);
			if ($parts === false || count($parts) < 5)
				return;

			$this->discInfo = [
				'size' => $parts[1],
				'used' => $parts[2],
				'available' => $parts[3],
				'percent' => $parts[4]
			];
		}
	}

}