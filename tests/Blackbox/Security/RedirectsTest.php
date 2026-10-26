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

namespace Tests\Blackbox\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * A path with a slash at the end is sent to the path without it. The new address must always stay on this site:
 * "//host" and "/\host" are addresses of other sites for a browser.
 */
#[Group('blackbox')]
class RedirectsTest extends BlackboxTestCase
{
	/**
	 * @return array<string,array{string}>
	 */
	public static function paths(): array
	{
		return [
			'a page'                  => ['/login/'],
			'two slashes and a host'  => ['//evil.example/'],
			'many slashes'            => ['////evil.example/'],
			'slash, backslash, host'  => ['/\\evil.example/'],
			'only slashes'            => ['///'],
			'a path of several parts' => ['/a//b/'],
		];
	}

	#[DataProvider('paths')]
	public function testTheSlashAtTheEndNeverLeadsToAnotherSite(string $path): void
	{
		$response = $this->rawGet($path);

		static::assertContains($response['status'], [301, 302]);
		static::assertMatchesRegularExpression('~^/(?![/\\\\])~', $response['headers']['location']);
	}
}
