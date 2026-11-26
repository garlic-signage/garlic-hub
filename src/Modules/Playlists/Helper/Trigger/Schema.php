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

	public function __construct()
	{
		$this->triggers = [
			'wallclocks' => $this->wallclockTrigger(...),
			'accesskeys' => $this->accesskeyTrigger(...),
			'touches'    => $this->touchTrigger(...),
			'notifies'   => $this->notifyTrigger(...),
		];

		$this->actions = [
			0 => $this->playItemAction(...),
			1 => $this->endCurrentItemAction(...),
		];
	}

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
			$sent  = $entry[$field] ?? 0;
			$value = $this->toInt($sent === '' ? 0 : $sent, 0, $max);
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
	$data = is_array($entry['data'] ?? null) ? $entry['data'] : $entry;

	$action = $this->toInt($data['selected_action'] ?? null, 0, 1);
	if ($action === null) {
		return null;
	}

	if ($action === 0) {
		$itemId = $this->toInt($data['touch_item_id'] ?? null, 1, PHP_INT_MAX);

		return $itemId !== null ? [
			'action' => 0,
			'data'   => [
				'touch_item_id'   => $itemId,
				'selected_action' => 0,
			],
		] : null;
	}

	return [
		'action' => 1,
		'data'   => [
			'selected_action' => 1,
		],
	];
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
		if (preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})T([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/D', $value, $m) !== 1)
			return false;

		return checkdate((int) $m['month'], (int) $m['day'], (int) $m['year']);
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

	
	// --- TRIGGERS (array = enabled, null = disabled, false = invalid) ---

	/** @param array<string,mixed> $entry */
	private function touchTrigger(array $entry): array|false|null
	{
		if (!isset($entry['selected_action'])) {
			return false;
		}

		$action = (int)$entry['selected_action'];
		$itemId = (int)($entry['touch_item_id'] ?? 0);

		// Action 0 (Play item / Show when click x) vaatii kohde-ID:n
		if ($action === 0) {
			return $itemId > 0 ? [
				'action' => 0,
				'data'   => [
					'touch_item_id'   => $itemId,
					'selected_action' => 0,
				],
			] : false;
		}

		// Action 1 (End current / next) ei käytä kohde-ID:tä
		if ($action === 1) {
			return [
				'action' => 1,
				'data'   => [
					'selected_action' => 1,
				],
			];
		}

		return false;
	}


	/** @param array<string,mixed> $entry */
	private function wallclockTrigger(array $entry): array|false|null
	{
		if (empty($entry['iso_date'])) {
			return null;
		}

		return [
			'action' => (int)($entry['selected_action'] ?? 0),
			'data'   => $entry,
		];
	}

	/** @param array<string,mixed> $entry */
	private function accesskeyTrigger(array $entry): array|false|null
	{
		if (!isset($entry['accesskey']) || !is_string($entry['accesskey'])) {
			return false;
		}

		return $entry['accesskey'] !== '' ? [
			'action' => (int)($entry['selected_action'] ?? 0),
			'data'   => ['accesskey' => $entry['accesskey']],
		] : null;
	}

	/** @param array<string,mixed> $entry */
	private function notifyTrigger(array $entry): array|false|null
	{
		if (!isset($entry['notify']) || !is_string($entry['notify'])) {
			return false;
		}

		return $entry['notify'] !== '' ? [
			'action' => (int)($entry['selected_action'] ?? 0),
			'data'   => ['notify' => $entry['notify']],
		] : null;
	}


		// --- ACTIONS ---

/** @param array<string,mixed> $raw */
	private function playItemAction(string $key, array $raw): bool
	{
		if ($key === 'touches') {
			$itemId = (int)($raw['touch_item_id'] ?? 0);

			// Action 0: kohde-ID:n täytyy olla > 0 ja kuulua saman soittolistan sallittuihin kohteisiin
			if ($itemId <= 0 || !in_array($itemId, $this->allowedTouchIds, true)) {
				return false;
			}

			$this->begin[$key][] = [
				'selected_action' => 0,
				'touch_item_id'   => $itemId,
			];
			return true;
		}

		//$this->begin[$key][] = $raw;
		return false;
	}

	/** @param array<string,mixed> $raw */
	private function endCurrentItemAction(string $key, array $raw): bool
	{
		if ($key === 'touches') {
			$this->end[$key][] = [
				'selected_action' => 1,
			];
			return true;
		}

		//$this->end[$key][] = $raw;
		return false;
	}
}

