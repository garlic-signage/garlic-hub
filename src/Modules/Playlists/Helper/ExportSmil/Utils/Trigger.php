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
			$determined[] = 'accesskey('.$accessKey['accesskey'].')';
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
			$determined[] = Base::ID_PREFIX.$touch['touch_item_id'].'.activateEvent';
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
			$determined[] = 'notify('.$notify['notify'].')';
		}

		return $determined;
	}

	/**
	 * @param array<string,mixed> $wallclock
	 */
	private function determineOneWallClock(array $wallclock): string
	{
		$repeats = $intervals = '';
		if ($wallclock['repeat_counts'] != -1)
		{
			if ($wallclock['repeat_counts'] == 0)
				$repeats = 'R/';
			else
				$repeats = 'R'.$wallclock['repeat_counts'].'/';

			$intervals = '/P';
			if ($wallclock['repeat_years'] > 0)
				$intervals .= $wallclock['repeat_years'].'Y';
			if ($wallclock['repeat_months'] > 0)
				$intervals .= $wallclock['repeat_months'].'M';
			if ($wallclock['repeat_weeks'] > 0)
				$intervals .= $wallclock['repeat_weeks'].'W';
			if ($wallclock['repeat_days'] > 0)
				$intervals .= $wallclock['repeat_days'].'D';
			if ($wallclock['repeat_hours'] > 0 OR $wallclock['repeat_minutes'] > 0)
			{
				$intervals .= 'T';
				if ($wallclock['repeat_hours'] > 0)
					$intervals .= $wallclock['repeat_hours'].'H';
				if ($wallclock['repeat_minutes'] > 0)
					$intervals .= $wallclock['repeat_minutes'].'M';
			}

			if (strlen($intervals) == 2) // if repeat_counts are set, but interval selected
			{
				$intervals = '';
				$repeats = '';
			}
		}
		if ($wallclock['weekday'] != 0 && $wallclock['weekday'] >= -7 && $wallclock['weekday'] <= 7)
		{
			$sign = $wallclock['weekday'] < 0 ? '-' : '+';
			$weekday = $sign . 'w' . abs((int) $wallclock['weekday']);
			$iso = str_replace('T', $weekday. 'T', $wallclock['iso_date']);
		}
		else
			$iso = $wallclock['iso_date'];

		return 'wallclock('.$repeats.$iso.$intervals.')';
	}

}