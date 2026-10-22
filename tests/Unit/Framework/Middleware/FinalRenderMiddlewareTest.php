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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

class FinalRenderMiddlewareTest extends TestCase
{
	private AdapterInterface&MockObject $templateEngineMock;
	private LayoutDataProviderInterface&MockObject $layoutDataProviderMock;
	private ServerRequestInterface&Stub $requestStub;
	private RequestHandlerInterface&Stub $handlerStub;
	private FinalRenderMiddleware $middleware;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->templateEngineMock     = $this->createMock(AdapterInterface::class);
		$this->layoutDataProviderMock = $this->createMock(LayoutDataProviderInterface::class);
		$this->requestStub            = $this->createStub(ServerRequestInterface::class);
		$this->handlerStub            = $this->createStub(RequestHandlerInterface::class);

		$this->middleware = new FinalRenderMiddleware($this->templateEngineMock, $this->layoutDataProviderMock, new StreamFactory());
	}

	#[Group('units')]
	public function testPassesOtherResponsesUnchanged(): void
	{
		$redirect = new Response(302)->withHeader('Location', '/login');
		$this->handlerStub->method('handle')->willReturn($redirect);
		$this->templateEngineMock->expects($this->never())->method('render');
		$this->layoutDataProviderMock->expects($this->never())->method('collect');

		static::assertSame($redirect, $this->middleware->process($this->requestStub, $this->handlerStub));
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
		$this->layoutDataProviderMock->expects($this->once())->method('collect')->with($this->requestStub)
			->willReturn(['main_menu' => ['menu'], 'LANG_PAGE_TITLE' => 'Default title']);

		$this->templateEngineMock->expects($this->exactly(2))->method('render')
			->willReturnCallback(static fn(string $template, array $data): string => match ($template)
			{
				'module/page'         => 'content of ' . $data['key'],
				'layouts/main_layout' => $data['LANG_PAGE_TITLE'] . '|' . $data['MAIN_CONTENT'] . '|' . $data['main_menu'][0],
				default               => static::fail('unexpected template ' . $template)
			});

		$response = $this->middleware->process($this->requestStub, $this->handlerStub);

		// page values win over the layout defaults
		static::assertSame('Page title|content of value|menu', (string) $response->getBody());
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame(201, $response->getStatusCode());
	}
}
