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

namespace Tests\Unit\Framework\TemplateEngine;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\TemplateEngine\PageResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PageResponseTest extends TestCase
{
	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCarriesPageData(): void
	{
		$response = new PageResponse([
			'main_layout' => ['LANG_PAGE_TITLE' => 'Title'],
			'this_layout' => ['template' => 'module/page', 'data' => ['key' => 'value']]
		]);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('module/page', $response->getTemplate());
		static::assertSame(['key' => 'value'], $response->getTemplateData());
		static::assertSame(['LANG_PAGE_TITLE' => 'Title'], $response->getMainLayoutData());
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testMissingOptionalPartsBecomeEmpty(): void
	{
		$response = new PageResponse(['this_layout' => ['template' => 'module/page']], 422);

		static::assertSame(422, $response->getStatusCode());
		static::assertSame([], $response->getTemplateData());
		static::assertSame([], $response->getMainLayoutData());
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testKeepsTypeAndDataOnWithHeader(): void
	{
		$response = new PageResponse(['this_layout' => ['template' => 'module/page']]);

		$changed = $response->withHeader('X-Test', '1');

		static::assertInstanceOf(PageResponse::class, $changed);
		static::assertSame('module/page', $changed->getTemplate());
	}

	#[Group('units')]
	public function testThrowsWithoutTemplate(): void
	{
		$this->expectException(FrameworkException::class);

		new PageResponse(['main_layout' => [], 'this_layout' => ['data' => []]]);
	}
}
