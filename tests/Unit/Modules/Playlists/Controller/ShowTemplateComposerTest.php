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

namespace Tests\Unit\Modules\Playlists\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Security\CsrfToken;
use App\Modules\Playlists\Controller\ShowTemplateComposer;
use App\Modules\Playlists\Helper\Templates\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

class ShowTemplateComposerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private Messages&MockObject $flashMock;
	private ShowTemplateComposer $controller;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock = $this->createMock(Orchestrator::class);
		$this->csrfTokenMock    = $this->createMock(CsrfToken::class);
		$this->flashMock        = $this->createMock(Messages::class);

		$this->controller = new ShowTemplateComposer(
			$this->orchestratorMock,
			new JsonResponseHandler(),
			$this->csrfTokenMock,
			$this->flashMock
		);
	}

	#[Group('units')]
	public function testShowWithoutItemId(): void
	{
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'Item ID not valid.');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), []);

		$this->assertRedirectedToPlaylists($response);
	}

	#[Group('units')]
	public function testShowWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkRights')->with(9)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('build');
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'No rights');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['item_id' => '9']);

		$this->assertRedirectedToPlaylists($response);
	}

	#[Group('units')]
	public function testShow(): void
	{
		$composer = ['main_layout' => ['title' => 'x'], 'this_layout' => ['template' => 'templates/composer', 'data' => ['key' => 'value']]];
		$this->orchestratorMock->expects($this->once())->method('checkRights')->with(9)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('build')->willReturn($composer);
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['item_id' => '9']);

		static::assertSame(200, $response->getStatusCode());
		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame($composer['this_layout']['template'], $response->getTemplate());
		static::assertSame($composer['this_layout']['data'], $response->getTemplateData());
		static::assertSame($composer['main_layout'], $response->getMainLayoutData());
	}

	#[Group('units')]
	public function testSaveWithCsrfMismatch(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('wrong')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->save($this->createRequest(['csrf_token' => 'wrong', 'item_id' => 9]), new Response());

		$this->assertJsonError('CSRF token mismatch.', $response);
	}

	#[Group('units')]
	public function testSaveWithoutRights(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('checkRights')->with(9)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('save');
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->save($this->createRequest(['csrf_token' => 'token', 'item_id' => 9]), new Response());

		$this->assertJsonError('No rights', $response);
	}

	#[Group('units')]
	public function testSaveFails(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('checkRights')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('save')->with(9, '{}', 'img')->willReturn(0);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->save(
			$this->createRequest(['csrf_token' => 'token', 'item_id' => 9, 'content' => '{}', 'image' => 'img']),
			new Response()
		);

		$this->assertJsonError('Save failed', $response);
	}

	#[Group('units')]
	public function testSave(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('checkRights')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('save')->with(9, '{}', '')->willReturn(1);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->save($this->createRequest(['csrf_token' => 'token', 'item_id' => 9, 'content' => '{}']), new Response());

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('{"success":true}', (string) $response->getBody());
	}

	#[Group('units')]
	public function testLoadWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkRights')->with(9)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('getContent');
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->load($this->createStub(ServerRequestInterface::class), new Response(), ['item_id' => '9']);

		$this->assertJsonError('No rights', $response);
	}

	#[Group('units')]
	public function testLoad(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkRights')->with(9)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('getContent')->willReturn('{}');
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->load($this->createStub(ServerRequestInterface::class), new Response(), ['item_id' => '9']);

		static::assertSame('{"success":true,"content":"{}"}', (string) $response->getBody());
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function createRequest(array $post): ServerRequestInterface
	{
		$request = $this->createStub(ServerRequestInterface::class);
		$request->method('getParsedBody')->willReturn($post);

		return $request;
	}

	private function assertJsonError(string $message, ResponseInterface $response): void
	{
		static::assertSame(400, $response->getStatusCode());
		static::assertSame(json_encode(['success' => false, 'error_message' => $message]), (string) $response->getBody());
	}

	private function assertRedirectedToPlaylists(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/playlists', $response->getHeaderLine('Location'));
	}
}
