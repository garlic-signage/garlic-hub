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

namespace App\Framework\Utils\Datatable;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use DateInterval;
use DateTime;

/**
 * Create human-readable times
 */
class TimeUnitsCalculator
{
	private int $lastAccessTimeStamp = 0;
	private DateInterval $interval;
	private Translator $translator;

	public function __construct(Translator $translator)
	{
		$this->translator = $translator;
	}

	/**
	 * @throws FrameworkException
	 */
	public function calculateLastAccess(DateTime $currentTime, DateTime $lastAccess): static
	{
		$this->lastAccessTimeStamp = $currentTime->getTimestamp() - $lastAccess->getTimestamp();
		if ($this->lastAccessTimeStamp < 0)
			throw new FrameworkException('Negative time difference.');

		$this->interval = $currentTime->diff($lastAccess);

		return $this;
	}

	public function getLastAccessTimeStamp(): int
	{
		return $this->lastAccessTimeStamp;
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function printDistance(): string
	{
		if ($this->interval->y > 0)
		{
			$number = $this->interval->y;
			$time_unit = 'years';
		}
		elseif ($this->interval->m > 0)
		{
			$number = $this->interval->m;
			$time_unit = 'months';
		}
		elseif ($this->interval->d > 0)
		{
			$number = $this->interval->d;
			$time_unit = 'days';
		}
		elseif ($this->interval->h > 0) {
			$number = $this->interval->h;
			$time_unit = 'hours';
		}
		elseif ($this->interval->i > 0)
		{
			$number = $this->interval->i;
			$time_unit = 'minutes';
		}
		else
		{
			$number = $this->interval->s;
			$time_unit = 'seconds';
		}
		return $this->translator->translateArrayWithPlural($time_unit, 'time_unit_ago', 'main', $number);
	}

}