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

namespace App\Modules\Player\IndexCreation\Builder\Preparers;

use App\Modules\Player\Enums\PlayerModel;
use DateMalformedStringException;
use DateTime;
use Exception;

/**
 * The ScreenTimesPreparer class extends AbstractPreparer and implements PreparerInterface.
 * This class processes screen time data of player entities for specific models,
 * and prepares structured screen time strings for use in wall clock operations.
 */
class ScreenTimesPreparer extends AbstractPreparer implements PreparerInterface
{
	private string $current_date = 'now';
	/** @var string[]  */
	private array $begin         = [];
	/** @var string[]  */
	private array $end           = [];
	/** @var array<string,mixed>  */
	private array $screenTimes   = [];

	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function prepare(): array
	{
		if ($this->playerEntity->getModel() != PlayerModel::GARLIC)
			return [];

		$this->determineStandByTimes($this->playerEntity->getScreenTimes());
		if (!$this->hasValues())
			return [];

		return [[
				'BEGIN_WALLCLOCKS' => $this->getBeginWallClockString(),
				'END_WALLCLOCKS' => $this->getEndWallClockString()
			]];
	}

	public function hasValues(): bool
	{
		if (empty($this->begin) || empty($this->end))
			return false;

		return true;
	}

	public function getBeginWallClockString(): string
	{
		return implode(';', $this->begin);
	}

	public function getEndWallClockString(): string
	{
		return implode(';', $this->end);
	}

	/**
	 * @param array<string,mixed> $screenTimes
	 * @throws DateMalformedStringException
	 */
	private function determineStandByTimes(array $screenTimes): void
	{
		if (!$this->isScreenTimeValid($screenTimes))
			return;

		$wallclock = $this->simplifyWallclockDatePart();

		/** @var array{day:string, periods:array{start:string, end:string}} $weekdays */
		foreach($this->screenTimes as $weekdays)
		{
			$w             = (((int) $weekdays['day']) + 1);
			$day           = '+w'.$w.'T';

			/** @var array{start:string, end:string} $value */
			foreach ($weekdays['periods'] as $value)
			{
				$this->end[] = $wallclock.$day.$value['start'].':00/P1W)';
				if ($value['end'] != '00:00')
					$this->begin[] = $wallclock.$day.$value['end'].':00/P1W)';
			}
		}
	}

	/**
	 * @throws DateMalformedStringException
	 */
	private function simplifyWallclockDatePart(): string
	{
		// to make calculation in player easier
		$date = new DateTime($this->current_date);
		$date->modify('-1 month');

		return 'wallclock(R/'.$date->format('Y-m-d');
	}

	/**
	 * @param array<string,mixed> $screenTimes
	 */
	private function isScreenTimeValid(array $screenTimes): bool
	{
		if ($screenTimes == [])
			return false;

		$this->screenTimes = $screenTimes;
		return true;
	}


}