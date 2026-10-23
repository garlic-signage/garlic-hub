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

namespace App\Modules\Playlists\Helper\ConditionalPlay;

/**
 * Checks the conditional play data sent by the editor and reduces it to the known structure.
 *
 * The result is exactly what the SMIL export (Utils\Conditional) reads:
 *
 *  [
 *      'date'     => ['from' => 'Y-m-d'|'', 'until' => 'Y-m-d'|''],
 *      'time'     => ['from' => 'H:i[:s]'|'', 'until' => 'H:i[:s]'|''],
 *      'weekdays' => [<weekday bit> => ['from' => 0..96, 'until' => 0..96]]
 *  ]
 *
 * Weekday bits are 1 (sunday) to 64 (saturday). From and until are quarter hours of the day.
 * Unknown keys are dropped, a wrong format rejects the whole input.
 */
class Schema
{
	private const array WEEKDAY_BITS = [1, 2, 4, 8, 16, 32, 64];
	private const int MAX_QUARTER_HOURS = 96;

	/**
	 * @param array<mixed> $input
	 * @return array<string,mixed>|null empty array when no condition is set, null when the input is invalid
	 */
	public function parse(array $input): ?array
	{
		$date     = $this->parsePeriod($input['date'] ?? [], $this->isDate(...));
		$time     = $this->parsePeriod($input['time'] ?? [], $this->isTime(...));
		$weekdays = $this->parseWeekdays($input['weekdays'] ?? []);
		if ($date === null || $time === null || $weekdays === null)
			return null;

		if ($date === [] && $time === [] && $weekdays === [])
			return [];

		return [
			'date'     => $date === [] ? ['from' => '', 'until' => ''] : $date,
			'time'     => $time === [] ? ['from' => '', 'until' => ''] : $time,
			'weekdays' => $weekdays
		];
	}

	/**
	 * @return array{from:string, until:string}|array{}|null
	 */
	private function parsePeriod(mixed $period, callable $isValid): ?array
	{
		if (!is_array($period))
			return null;

		if ($period === [])
			return [];

		$result = [];
		foreach (['from', 'until'] as $key)
		{
			$value = $period[$key] ?? '';
			if (!is_string($value) || ($value !== '' && !$isValid($value)))
				return null;

			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * @return array<int,array{from:int, until:int}>|null
	 */
	private function parseWeekdays(mixed $weekdays): ?array
	{
		if (!is_array($weekdays))
			return null;

		$result = [];
		foreach ($weekdays as $bit => $range)
		{
			if (!in_array($bit, self::WEEKDAY_BITS, true) || !is_array($range))
				return null;

			$from  = $this->toQuarterHour($range['from'] ?? null);
			$until = $this->toQuarterHour($range['until'] ?? null);
			if ($from === null || $until === null || $from > $until)
				return null;

			$result[$bit] = ['from' => $from, 'until' => $until];
		}

		return $result;
	}

	private function toQuarterHour(mixed $value): ?int
	{
		if (is_string($value) && ctype_digit($value))
			$value = (int) $value;

		if (!is_int($value) || $value < 0 || $value > self::MAX_QUARTER_HOURS)
			return null;

		return $value;
	}

	private function isDate(string $value): bool
	{
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m) !== 1)
			return false;

		return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
	}

	private function isTime(string $value): bool
	{
		return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/D', $value) === 1;
	}
}
