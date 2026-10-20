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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConcreteService extends AbstractBaseService
{
	public function addSomeErrors(): void
	{
		$this->addErrorMessage('This is an Error');
	}
	public function addMultipleErrors(): void
	{
		$this->addErrorMessage('This is an Error');
		$this->addErrorMessage('Another error');

	}

	public function getUID(): int
	{
		return $this->UID;
	}
}
class AbstractBaseServiceTest extends TestCase
{
	private ConcreteService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$loggerStub = $this->createStub(LoggerInterface::class);
		$this->service = new ConcreteService($loggerStub);
	}

	#[Group('units')]
	public function testErrorMessages(): void
	{
		static::assertFalse($this->service->hasErrorMessages());
		static::assertEmpty($this->service->getErrorMessages());

		$this->service->addSomeErrors();

		static::assertTrue($this->service->hasErrorMessages());
		static::assertSame(['This is an Error'], $this->service->getErrorMessages());
	}

	#[Group('units')]
	public function testSetUID(): void
	{
		$this->service->setUID(123);

		static::assertSame(123, $this->service->getUID());
	}

	#[Group('units')]
	public function testGetErrorMessagesAsString(): void
	{
		static::assertSame('', $this->service->getErrorMessagesAsString());

		$this->service->addMultipleErrors();

		static::assertSame('This is an Error | Another error', $this->service->getErrorMessagesAsString());
	}


}
