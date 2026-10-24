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

namespace Tests\Unit\Framework\Services;

use App\Framework\Services\AbstractBaseService;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ConcreteService extends AbstractBaseService
{
	public function logSomething(): void
	{
		$this->logger->info('something');
	}
}

class AbstractBaseServiceTest extends TestCase
{
	#[Group('units')]
	public function testTheLoggerIsAvailableForSubclasses(): void
	{
		$logs = new TestHandler();

		new ConcreteService(new Logger('test', [$logs]))->logSomething();

		$records = $logs->getRecords();
		static::assertCount(1, $records);
		static::assertSame(Level::Info, $records[0]->level);
		static::assertSame('something', $records[0]->message);
	}
}
