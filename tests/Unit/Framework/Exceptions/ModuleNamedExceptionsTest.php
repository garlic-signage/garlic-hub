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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Core, Database, Framework and User exceptions differ only in the module name they carry.
 */
class ModuleNamedExceptionsTest extends TestCase
{
	/**
	 * @param class-string<BaseException> $class
	 */
	#[Group('units')]
	#[DataProvider('exceptionProvider')]
	public function testCarriesItsModuleNameMessageAndCode(string $class, string $moduleName): void
	{
		$exception = new $class('Test Message', 100);

		static::assertSame($moduleName, $exception->getModuleName());
		static::assertSame('Test Message', $exception->getMessage());
		static::assertSame(100, $exception->getCode());
		static::assertNull($exception->getPrevious());
	}

	/**
	 * @param class-string<BaseException> $class
	 */
	#[Group('units')]
	#[DataProvider('exceptionProvider')]
	public function testKeepsThePreviousException(string $class, string $moduleName): void
	{
		$previous  = new Exception('Previous Message');
		$exception = new $class('Test Message', 100, $previous);

		static::assertSame($previous, $exception->getPrevious());
		static::assertSame($moduleName, $exception->getModuleName());
	}

	/**
	 * @param class-string<BaseException> $class
	 */
	#[Group('units')]
	#[DataProvider('exceptionProvider')]
	public function testCodeDefaultsToZero(string $class, string $moduleName): void
	{
		$exception = new $class('Test Message');

		static::assertSame(0, $exception->getCode());
		static::assertSame($moduleName, $exception->getModuleName());
	}

	/**
	 * @return array<string, array{class-string<BaseException>, string}>
	 */
	public static function exceptionProvider(): array
	{
		return [
			'core'      => [CoreException::class, 'Core'],
			'database'  => [DatabaseException::class, 'DB'],
			'framework' => [FrameworkException::class, 'Framework'],
			'user'      => [UserException::class, 'User'],
		];
	}
}
