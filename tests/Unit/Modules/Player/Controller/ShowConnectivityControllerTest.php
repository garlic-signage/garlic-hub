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

namespace Tests\Unit\Modules\Player\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Player\Controller\ShowConnectivityController;
use App\Modules\Player\Helper\NetworkSettings\Orchestrator;
use App\Modules\Player\Helper\NetworkSettings\TemplatePreparer;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

class ShowConnectivityControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private Messages&MockObject $flashMock;
	private ShowConnectivityController $controller;

	/** @var array{player_id: int, player_name: string, is_intranet: int, api_endpoint: string} */
	private array $player = ['player_id' => 5, 'player_name' => 'Lobby', 'is_intranet' => 1, 'api_endpoint' => 'https://example.com'];

	/** @var array{hidden: list<mixed>, visible: list<mixed>} */
	private array $form = ['hidden' => [], 'visible' => []];

	/** @var array<string,array<string,string>> */
	private array $templateData = ['main_layout' => ['LANG_PAGE_TITLE' => 'x']];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->templatePreparerMock = $this->createMock(TemplatePreparer::class);
		$this->flashMock            = $this->createMock(Messages::class);

		$this->controller = new ShowConnectivityController($this->orchestratorMock, $this->templatePreparerMock, $this->flashMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShowRendersForm(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadPlayer')->with(5)->willReturn($this->player);
		$this->expectRendering($this->player);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['player_id' => '5']);

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShowWithoutPlayerIdRedirects(): void
	{
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'No player id given.');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), []);

		$this->assertRedirectedToPlayerList($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShowWithInaccessiblePlayerRedirects(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadPlayer')->with(5)->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('buildForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'Player ID not accessible.');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['player_id' => '5']);

		$this->assertRedirectedToPlayerList($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreWithInaccessiblePlayerRedirects(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadPlayer')->with(0)->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('store');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'Player ID not accessible.');

		$response = $this->controller->store($this->createRequest(['is_intranet' => 1]), new Response());

		$this->assertRedirectedToPlayerList($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreWithValidationErrorsRendersFormWithPost(): void
	{
		$post = ['player_id' => '5', 'is_intranet' => '1', 'api_endpoint' => ''];
		$this->orchestratorMock->expects($this->once())->method('loadPlayer')->with(5)->willReturn($this->player);
		$this->orchestratorMock->expects($this->once())->method('store')->with(5, $post)
			->willReturn(['success' => false, 'errors' => ['CSRF mismatch', 'No API endpoint.']]);
		$this->expectRendering($post);

		$messages = [];
		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnCallback(function (string $key, string $message) use (&$messages) { $messages[] = [$key, $message]; });
		$this->flashMock->expects($this->never())->method('addMessage');

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRendered($response);
		static::assertSame([['error', 'CSRF mismatch'], ['error', 'No API endpoint.']], $messages);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreSucceedsAndRedirects(): void
	{
		$post = ['player_id' => '5', 'is_intranet' => '1', 'api_endpoint' => 'https://example.com'];
		$this->orchestratorMock->expects($this->once())->method('loadPlayer')->with(5)->willReturn($this->player);
		$this->orchestratorMock->expects($this->once())->method('store')->with(5, $post)->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('buildForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'Player connectivity data successfully stored.');

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRedirectedToPlayerList($response);
	}

	/**
	 * @param array<string,mixed> $networkData
	 */
	private function expectRendering(array $networkData): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildForm')->with($networkData)->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepare')->with('Lobby', $this->form)
			->willReturn($this->templateData);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	private function createRequest(array $post): ServerRequestInterface
	{
		$request = $this->createStub(ServerRequestInterface::class);
		$request->method('getParsedBody')->willReturn($post);

		return $request;
	}

	private function assertRendered(ResponseInterface $response): void
	{
		static::assertSame(200, $response->getStatusCode());
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame(serialize($this->templateData), (string) $response->getBody());
	}

	private function assertRedirectedToPlayerList(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/player', $response->getHeaderLine('Location'));
	}
}
