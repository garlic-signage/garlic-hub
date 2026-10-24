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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;

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
	 * FinalRenderMiddleware sets the body and a header of the response it gets: it must still be the page.
	 *
	 * @param callable(PageResponse): \Psr\Http\Message\ResponseInterface $change
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('changeProvider')]
	public function testKeepsTypeAndDataOnEveryChange(callable $change): void
	{
		$response = new PageResponse([
			'main_layout' => ['LANG_PAGE_TITLE' => 'Title'],
			'this_layout' => ['template' => 'module/page', 'data' => ['key' => 'value']]
		], 201);

		$changed = $change($response);

		static::assertInstanceOf(PageResponse::class, $changed);
		static::assertNotSame($response, $changed);
		static::assertSame('module/page', $changed->getTemplate());
		static::assertSame(['key' => 'value'], $changed->getTemplateData());
		static::assertSame(['LANG_PAGE_TITLE' => 'Title'], $changed->getMainLayoutData());
	}

	/**
	 * @return array<string, array{callable(PageResponse): \Psr\Http\Message\ResponseInterface}>
	 */
	public static function changeProvider(): array
	{
		return [
			'withHeader'          => [static fn(PageResponse $r) => $r->withHeader('X-Test', '1')],
			'withAddedHeader'     => [static fn(PageResponse $r) => $r->withAddedHeader('X-Test', '1')],
			'withoutHeader'       => [static fn(PageResponse $r) => $r->withoutHeader('X-Test')],
			'withStatus'          => [static fn(PageResponse $r) => $r->withStatus(404)],
			'withBody'            => [static fn(PageResponse $r) => $r->withBody((new StreamFactory())->createStream('<html>'))],
			'withProtocolVersion' => [static fn(PageResponse $r) => $r->withProtocolVersion('2')],
		];
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheChangedResponseKeepsItsStatusAndBody(): void
	{
		$response = new PageResponse(['this_layout' => ['template' => 'module/page']], 201);

		$changed = $response->withBody((new StreamFactory())->createStream('<html>'))->withHeader('Content-Type', 'text/html');

		static::assertSame(201, $changed->getStatusCode());
		static::assertSame('<html>', (string) $changed->getBody());
		static::assertSame('', (string) $response->getBody(), 'The original response is not changed.');
	}

	/**
	 * @param array<string,mixed> $page
	 */
	#[Group('units')]
	#[DataProvider('invalidPageProvider')]
	public function testThrowsWithoutAUsableTemplate(array $page): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Page data needs this_layout.template.');

		new PageResponse($page);
	}

	/**
	 * @return array<string, array{array<string,mixed>}>
	 */
	public static function invalidPageProvider(): array
	{
		return [
			'no this_layout'        => [['main_layout' => []]],
			'this_layout no array'  => [['this_layout' => 'module/page']],
			'no template'           => [['main_layout' => [], 'this_layout' => ['data' => []]]],
			'template no string'    => [['this_layout' => ['template' => 5]]],
			'template is an array'  => [['this_layout' => ['template' => ['module/page']]]],
		];
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDataWhichIsNoArrayBecomesEmpty(): void
	{
		$response = new PageResponse(['main_layout' => 'title', 'this_layout' => ['template' => 'module/page', 'data' => 'text']]);

		static::assertSame([], $response->getTemplateData());
		static::assertSame([], $response->getMainLayoutData());
	}
}
