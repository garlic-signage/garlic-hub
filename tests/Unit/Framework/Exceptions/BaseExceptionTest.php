<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Exceptions;

use App\Framework\Exceptions\BaseException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class BaseExceptionTest extends TestCase
{
	#[Group('units')]
	public function testModuleNameIsEmptyByDefault(): void
	{
		static::assertSame('', (new BaseException('Test Exception'))->getModuleName());
	}

	#[Group('units')]
	public function testSetAndGetModuleName(): void
	{
		$exception = new BaseException('Test Exception');
		$exception->setModuleName('TestModule');

		static::assertSame('TestModule', $exception->getModuleName());
	}

	#[Group('units')]
	public function testSetModuleNameIsChainable(): void
	{
		$exception = new BaseException('Test Exception');

		static::assertSame($exception, $exception->setModuleName('TestModule'));
	}

	#[Group('units')]
	public function testGetDetails(): void
	{
		$line      = __LINE__ + 1;
		$exception = new BaseException('Test Exception', 123);
		$exception->setModuleName('TestModule');

		static::assertSame(
			[
				'module_name' => 'TestModule',
				'message'     => 'Test Exception',
				'code'        => 123,
				'file'        => __FILE__,
				'line'        => $line,
				'trace'       => $exception->getTraceAsString(),
			],
			$exception->getDetails()
		);
	}

	#[Group('units')]
	public function testGetDetailsAsString(): void
	{
		$exception = new BaseException('Test Exception', 123);
		$exception->setModuleName('TestModule');

		$expected = "Module: TestModule\n"
			. "Message: Test Exception\n"
			. "Code: 123\n"
			. 'File: ' . __FILE__ . ' (Line ' . $exception->getLine() . ")\n"
			. "Trace:\n" . $exception->getTraceAsString() . "\n";

		static::assertSame($expected, $exception->getDetailsAsString());
	}
}
