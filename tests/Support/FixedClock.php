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

namespace Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A clock which stands still until the test moves it: the time slots of the TOTP.
 */
final class FixedClock implements ClockInterface
{
	public function __construct(private int $timestamp) {}

	public function now(): DateTimeImmutable
	{
		return new DateTimeImmutable('@' . $this->timestamp);
	}

	public function moveBy(int $seconds): void
	{
		$this->timestamp += $seconds;
	}
}
