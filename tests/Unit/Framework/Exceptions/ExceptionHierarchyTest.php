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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\AuthException;
use App\Framework\Exceptions\BaseException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The error handler decides by instanceof which status and text an exception gets (JsonErrorHandler),
 * so the type hierarchy is part of the behaviour.
 */
class ExceptionHierarchyTest extends TestCase
{
	/**
	 * @param class-string $child
	 * @param class-string $parent
	 */
	#[Group('units')]
	#[DataProvider('hierarchyProvider')]
	public function testHierarchy(string $child, string $parent, bool $expected): void
	{
		static::assertSame($expected, is_a($child, $parent, true));
	}

	/**
	 * @return array<string, array{class-string, class-string, bool}>
	 */
	public static function hierarchyProvider(): array
	{
		return [
			'not found is translatable'         => [NotFoundException::class, TranslatableModuleException::class, true],
			'not found is a module exception'   => [NotFoundException::class, ModuleException::class, true],
			'not found is a base exception'     => [NotFoundException::class, BaseException::class, true],
			'access denied is translatable'     => [AccessDeniedException::class, TranslatableModuleException::class, true],
			'access denied is a module error'   => [AccessDeniedException::class, ModuleException::class, true],
			'translatable is a module error'    => [TranslatableModuleException::class, ModuleException::class, true],
			'not found is no access denied'     => [NotFoundException::class, AccessDeniedException::class, false],
			'access denied is no not found'     => [AccessDeniedException::class, NotFoundException::class, false],
			'plain module error is no translatable' => [ModuleException::class, TranslatableModuleException::class, false],
			'auth is a runtime exception'       => [AuthException::class, RuntimeException::class, true],
			'auth is no base exception'         => [AuthException::class, BaseException::class, false],
		];
	}

	/**
	 * @param class-string<TranslatableModuleException> $class
	 */
	#[Group('units')]
	#[DataProvider('translatableProvider')]
	public function testSubclassesCarryKeyAndReplacementsLikeTheirParent(string $class): void
	{
		$exception = new $class('playlists', 'playlist_not_found', ['playlist_id' => 5]);

		static::assertSame('playlists', $exception->getModuleName());
		static::assertSame('playlist_not_found', $exception->getTranslationKey());
		static::assertSame(['playlist_id' => 5], $exception->getReplacements());
	}

	/**
	 * @return array<string, array{class-string<TranslatableModuleException>}>
	 */
	public static function translatableProvider(): array
	{
		return [
			'not found'     => [NotFoundException::class],
			'access denied' => [AccessDeniedException::class],
		];
	}

	#[Group('units')]
	public function testAuthExceptionKeepsMessageAndCode(): void
	{
		$exception = new AuthException('Login failed', 401);

		static::assertSame('Login failed', $exception->getMessage());
		static::assertSame(401, $exception->getCode());
	}
}
