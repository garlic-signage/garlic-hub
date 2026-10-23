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

namespace Tests\Unit\Framework\Http;

use App\Framework\Http\Https;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class HttpsTest extends TestCase
{
	/** @var array<string,mixed> */
	private array $serverBackup;

	protected function setUp(): void
	{
		$this->serverBackup = $_SERVER;
		unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->serverBackup;
	}

	/**
	 * @param array<string,mixed> $server
	 */
	#[Group('units')]
	#[DataProvider('serverProvider')]
	public function testIsActive(array $server, bool $expected): void
	{
		$_SERVER = array_merge($_SERVER, $server);

		static::assertSame($expected, Https::isActive());
	}

	/**
	 * @return array<string,array{array<string,mixed>,bool}>
	 */
	public static function serverProvider(): array
	{
		return [
			'plain http'          => [[], false],
			'https on'            => [['HTTPS' => 'on'], true],
			'https off (IIS)'     => [['HTTPS' => 'off'], false],
			'https empty'         => [['HTTPS' => ''], false],
			'port 443'            => [['SERVER_PORT' => '443'], true],
			'port 80'             => [['SERVER_PORT' => '80'], false],
			'proxy https'         => [['HTTP_X_FORWARDED_PROTO' => 'https'], true],
			'proxy http'          => [['HTTP_X_FORWARDED_PROTO' => 'http'], false],
		];
	}
}
