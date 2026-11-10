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

namespace App\Modules\Playlists\Helper\ExportSmil\Utils;

use App\Framework\Helper\Xml;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;

/**
 * The Trigger class processes a set of triggers, determines their type, and generates corresponding representations.
 */
class Trigger
{
	/** @var array<string,string>  */
	const array SUPPORTED_TRIGGER = [
		'wallclocks' => 'parseWallClocks',
		'accesskeys' => 'parseAccesskeys',
		'touches'    => 'parseTouches',
		'notifies'   => 'parseNotifies',
	];

	/** @var array<string,mixed>  */
	private array $triggers;

	/**
	 * @param array<string,mixed> $triggers
	 */
	public function __construct(array $triggers)
	{
		$this->triggers = $triggers;
	}

	public function hasTriggers(): bool
	{
		return !empty($this->triggers);
	}

	public function determineTrigger(): string
	{
		$existingTriggers = array_intersect_key(self::SUPPORTED_TRIGGER, $this->triggers);

		$ar = array_reduce(array_keys($existingTriggers), function (array $carry, string $key): array
			{

				return array_merge($carry, $this->{self::SUPPORTED_TRIGGER[$key]}($this->triggers[$key]));
			},[]
		);

		return implode(';', $ar);
	}

	/**
	 * @param list<array<string,string>> $wallclocks
	 * @return string[]
	 */
	private function parseWallClocks(array $wallclocks): array
	{
		$determined = [];
		foreach ($wallclocks as $wallclock)
		{
			$determined[] = $this->determineOneWallClock($wallclock);
		}

		return $determined;
	}

	/**
	 * @param list<array{accesskey:string}> $accessKeys
	 * @return string[]
	 */
	private function parseAccesskeys(array $accessKeys): array
	{
		$determined = [];
		foreach ($accessKeys as $accessKey)
		{
			$determined[] = 'accesskey('.Xml::escape($accessKey['accesskey']).')';
		}

		return $determined;
	}

	/**
	 * @param list<array{touch_item_id:string}> $touches
	 * @return string[]
	 */
	private function parseTouches(array $touches): array
	{
		$determined = [];
		foreach ($touches as $touch)
		{
			$determined[] = Base::ID_PREFIX.(int) $touch['touch_item_id'].'.activateEvent';
		}

		return $determined;
	}

	/**
	 * @param list<array{notify:string}> $notifies
	 * @return string[]
	 */
	private function parseNotifies(array $notifies): array
	{
		$determined = [];
		foreach ($notifies as $notify)
		{
			$determined[] = 'notify('.Xml::escape($notify['notify']).')';
		}

		return $determined;
	}

	/**
	 * @param array<string,mixed> $wallclock
	 */
	private function determineOneWallClock(array $wallclock): string
	{
		// the values come from the database: only numbers and an escaped date can reach the SMIL file
		$counts  = (int) ($wallclock['repeat_counts'] ?? -1);
		$weekday = $this->number($wallclock, 'weekday');
		$isoDate = Xml::escape((string) ($wallclock['iso_date'] ?? ''));

		$repeats = $intervals = '';
		if ($counts != -1)
		{
			if ($counts == 0)
				$repeats = 'R/';
			else
				$repeats = 'R'.$counts.'/';

			$hours   = $this->number($wallclock, 'repeat_hours');
			$minutes = $this->number($wallclock, 'repeat_minutes');

			$intervals = '/P';
			foreach (['repeat_years' => 'Y', 'repeat_months' => 'M', 'repeat_weeks' => 'W', 'repeat_days' => 'D'] as $field => $unit)
			{
				$value = $this->number($wallclock, $field);
				if ($value > 0)
					$intervals .= $value.$unit;
			}
			if ($hours > 0 OR $minutes > 0)
			{
				$intervals .= 'T';
				if ($hours > 0)
					$intervals .= $hours.'H';
				if ($minutes > 0)
					$intervals .= $minutes.'M';
			}

			if (strlen($intervals) == 2) // if repeat_counts are set, but interval selected
			{
				$intervals = '';
				$repeats = '';
			}
		}
		if ($weekday != 0 && $weekday >= -7 && $weekday <= 7)
		{
			// @infection-ignore-all the weekday 0 is excluded above
			$sign = $weekday < 0 ? '-' : '+';
			$iso = str_replace('T', $sign . 'w' . abs($weekday) . 'T', $isoDate);
		}
		else
			$iso = $isoDate;

		return 'wallclock('.$repeats.$iso.$intervals.')';
	}

	/**
	 * @param array<string,mixed> $wallclock
	 */
	private function number(array $wallclock, string $field): int
	{
		return (int) ($wallclock[$field] ?? 0);
	}

}