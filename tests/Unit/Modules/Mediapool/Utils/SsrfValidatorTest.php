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

namespace Tests\Unit\Modules\Mediapool\Utils;

use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Utils\SsrfValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SsrfValidatorTest extends TestCase
{
	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidUrlProvider(): array
	{
		return [
			'no host'           => ['not a url'],
			'scheme not allowed' => ['ftp://example.com/file.jpg'],
		];
	}

	#[Group('units')]
	#[DataProvider('invalidUrlProvider')]
	public function testInvalidUrlThrowsTranslatableException(string $url): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('url_invalid');

		(new SsrfValidator())->validateAndResolveUrl($url);
	}
}
