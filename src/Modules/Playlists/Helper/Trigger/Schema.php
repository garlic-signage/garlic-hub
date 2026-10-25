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

namespace App\Modules\Playlists\Helper\Trigger;

/**
 * The begin trigger of an item as the trigger form sends it: wallclocks, accesskeys, touches and notifies.
 * The values go into attributes of the SMIL file of the players, so everything else than the known fields with
 * their values is rejected. The numbers are returned as integers.
 */
class Schema
{
	private const array REPEAT_LIMITS = [
		'repeat_minutes' => 59,
		'repeat_hours'   => 23,
		'repeat_days'    => 7,
		'repeat_weeks'   => 4,
		'repeat_months'  => 11,
		'repeat_years'   => 11,
	];

	/**
	 * @param array<mixed> $input the body of the request, other fields than the triggers (csrf_token, item_id) are dropped
	 * @return array<string,list<array<string,int|string>>>|null null when a value is not valid
	 */
	public function parse(array $input): ?array
	{
		$parsers = [
			'wallclocks' => $this->parseWallclock(...),
			'accesskeys' => $this->parseAccesskey(...),
			'touches'    => $this->parseTouch(...),
			'notifies'   => $this->parseNotify(...),
		];

		$result = [];
		foreach ($parsers as $name => $parser)
		{
			if (!array_key_exists($name, $input))
				continue;

			if (!is_array($input[$name]))
				return null;

			$entries = [];
			foreach ($input[$name] as $entry)
			{
				$parsed = is_array($entry) ? $parser($entry) : null;
				if ($parsed === null)
					return null;

				$entries[] = $parsed;
			}

			if ($entries !== [])
				$result[$name] = $entries;
		}

		return $result;
	}

	/**
	 * @param array<mixed> $entry
	 * @return array<string,int|string>|null
	 */
	private function parseWallclock(array $entry): ?array
	{
		$isoDate = $entry['iso_date'] ?? null;
		if (!is_string($isoDate) || !$this->isIsoDate($isoDate))
			return null;

		$weekday = $this->toInt($entry['weekday'] ?? 0, -7, 7, true);
		$counts  = $this->toInt($entry['repeat_counts'] ?? -1, -1, 9999);
		if ($weekday === null || $counts === null)
			return null;

		$wallclock = ['iso_date' => $isoDate, 'weekday' => $weekday, 'repeat_counts' => $counts];
		foreach (self::REPEAT_LIMITS as $field => $max)
		{
			// an emptied number field of the form is no repeat
			$value = $this->toInt(($entry[$field] ?? 0) === '' ? 0 : ($entry[$field] ?? 0), 0, $max);
			if ($value === null)
				return null;

			$wallclock[$field] = $value;
		}

		return $wallclock;
	}

	/**
	 * @param array<mixed> $entry
	 * @return array<string,string>|null
	 */
	private function parseAccesskey(array $entry): ?array
	{
		$key = $entry['accesskey'] ?? null;

		return is_string($key) && preg_match('/^[a-zA-Z0-9]$/D', $key) === 1 ? ['accesskey' => $key] : null;
	}

	/**
	 * @param array<mixed> $entry
	 * @return array<string,int>|null
	 */
	private function parseTouch(array $entry): ?array
	{
		$itemId = $this->toInt($entry['touch_item_id'] ?? null, 1, PHP_INT_MAX);

		return $itemId === null ? null : ['touch_item_id' => $itemId];
	}

	/**
	 * @param array<mixed> $entry
	 * @return array<string,string>|null
	 */
	private function parseNotify(array $entry): ?array
	{
		$notify = $entry['notify'] ?? null;

		return is_string($notify) && preg_match('/^[a-zA-Z0-9]{1,64}$/D', $notify) === 1 ? ['notify' => $notify] : null;
	}

	private function isIsoDate(string $value): bool
	{
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/D', $value, $m) !== 1)
			return false;

		return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
	}

	/**
	 * A whole number as number or as text, the weekday may have a sign in front ("+3").
	 */
	private function toInt(mixed $value, int $min, int $max, bool $signed = false): ?int
	{
		if (is_string($value) && preg_match($signed ? '/^[+-]?\d{1,4}$/D' : '/^-?\d{1,19}$/D', $value) === 1)
			$value = (int) $value;

		return is_int($value) && $value >= $min && $value <= $max ? $value : null;
	}
}
