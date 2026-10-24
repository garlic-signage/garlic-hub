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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Middleware\FinalRenderMiddleware;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Framework\TemplateEngine\LayoutDataProviderInterface;
use App\Framework\TemplateEngine\PageResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

class FinalRenderMiddlewareTest extends TestCase
{
	private AdapterInterface&Stub $templateEngineStub;
	private LayoutDataProviderInterface&Stub $layoutDataProviderStub;
	private ServerRequestInterface&Stub $requestStub;
	private RequestHandlerInterface&Stub $handlerStub;
	private FinalRenderMiddleware $middleware;

	protected function setUp(): void
	{
		parent::setUp();
		$this->requestStub            = $this->createStub(ServerRequestInterface::class);
		$this->handlerStub            = $this->createStub(RequestHandlerInterface::class);
		$this->layoutDataProviderStub = $this->createStub(LayoutDataProviderInterface::class);
		// the page and the layout are rendered as text which shows what was put in
		$this->templateEngineStub = $this->createStub(AdapterInterface::class);
		$this->templateEngineStub->method('render')->willReturnCallback(
			static fn(string $template, array $data): string => $template . ':' . json_encode($data)
		);

		$this->middleware = new FinalRenderMiddleware($this->templateEngineStub, $this->layoutDataProviderStub, new StreamFactory());
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('otherResponseProvider')]
	public function testPassesOtherResponsesUnchanged(ResponseInterface $response): void
	{
		$this->handlerStub->method('handle')->willReturn($response);

		static::assertSame($response, $this->middleware->process($this->requestStub, $this->handlerStub));
	}

	/**
	 * @return array<string, array{ResponseInterface}>
	 */
	public static function otherResponseProvider(): array
	{
		$json = new Response(200)->withHeader('Content-Type', 'application/json');
		$json->getBody()->write('{"success":true}');

		return [
			'redirect'   => [new Response(302)->withHeader('Location', '/login')],
			'json'       => [$json],
			'no content' => [new Response(204)],
		];
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testRendersPageIntoMainLayout(): void
	{
		$this->handlerStub->method('handle')->willReturn(new PageResponse([
			'main_layout' => ['LANG_PAGE_TITLE' => 'Page title'],
			'this_layout' => ['template' => 'module/page', 'data' => ['key' => 'value']]
		], 201));
		$this->layoutDataProviderStub->method('collect')->willReturn(['main_menu' => ['menu'], 'LANG_PAGE_TITLE' => 'Default title']);

		$response = $this->middleware->process($this->requestStub, $this->handlerStub);

		// the page is rendered with its data, the layout gets the page, the menu of the provider and the title of the page
		static::assertSame(
			'layouts/main_layout:' . json_encode([
				'main_menu'       => ['menu'],
				'LANG_PAGE_TITLE' => 'Page title',
				'MAIN_CONTENT'    => 'module/page:{"key":"value"}',
			]),
			(string) $response->getBody()
		);
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame(201, $response->getStatusCode());
	}

	/**
	 * The layout data of the provider is collected for the request which is handled.
	 *
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheLayoutDataIsCollectedForTheRequest(): void
	{
		$this->handlerStub->method('handle')->willReturn(new PageResponse([
			'main_layout' => [],
			'this_layout' => ['template' => 'module/page', 'data' => []]
		]));
		$request = $this->requestStub;
		$this->layoutDataProviderStub->method('collect')->willReturnCallback(
			static fn(ServerRequestInterface $given): array => ['same_request' => $given === $request]
		);

		$response = $this->middleware->process($this->requestStub, $this->handlerStub);

		static::assertStringContainsString('"same_request":true', (string) $response->getBody());
	}

	/**
	 * The main layout data of a page is applied last, it wins even over the rendered content.
	 *
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheMainLayoutDataOfAPageWinsOverEverything(): void
	{
		$this->handlerStub->method('handle')->willReturn(new PageResponse([
			'main_layout' => ['MAIN_CONTENT' => 'set by the page'],
			'this_layout' => ['template' => 'module/page', 'data' => []]
		]));
		$this->layoutDataProviderStub->method('collect')->willReturn([]);

		$response = $this->middleware->process($this->requestStub, $this->handlerStub);

		static::assertSame('layouts/main_layout:{"MAIN_CONTENT":"set by the page"}', (string) $response->getBody());
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testHeadersOfThePageAreKept(): void
	{
		$this->handlerStub->method('handle')->willReturn(
			new PageResponse(['main_layout' => [], 'this_layout' => ['template' => 'module/page', 'data' => []]])
				->withHeader('X-Custom', 'yes')
		);
		$this->layoutDataProviderStub->method('collect')->willReturn([]);

		$response = $this->middleware->process($this->requestStub, $this->handlerStub);

		static::assertSame('yes', $response->getHeaderLine('X-Custom'));
	}
}
