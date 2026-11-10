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

namespace App\Framework\Database\SlowQueries;

use Psr\Log\LoggerInterface;

/**
 * Measures the queries and writes those which take longer than the threshold into the log, with the path of the
 * request: SQLite has no log of slow queries of its own. Only the SQL is written, not the values of the parameters.
 */
readonly class SlowQueryLog
{
	/** @var \Closure(): float the time in milliseconds */
	private \Closure $clock;

	/**
	 * @param (\Closure(): float)|null $clock milliseconds, for the tests
	 */
	public function __construct(private LoggerInterface $logger, private int $thresholdMs, ?\Closure $clock = null)
	{
		$this->clock = $clock ?? static fn(): float => hrtime(true) / 1_000_000;
	}

	/**
	 * @template T
	 * @param callable(): T $query
	 * @return T
	 */
	public function measure(string $sql, callable $query): mixed
	{
		$start = ($this->clock)();
		try
		{
			return $query();
		}
		finally
		{
			$duration = ($this->clock)() - $start;
			if ($duration >= $this->thresholdMs)
				$this->logger->warning('{duration} ms {path}: {sql}', ['duration' => (int) round($duration), 'path' => self::path(), 'sql' => $sql]);
		}
	}

	private static function path(): string
	{
		$uri = $_SERVER['REQUEST_URI'] ?? null;
		if (!is_string($uri))
			return 'cli';

		$path = parse_url($uri, PHP_URL_PATH);

		return is_string($path) ? $path : 'cli';
	}
}
