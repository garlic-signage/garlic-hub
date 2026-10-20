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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Controller\ShowSettingsController;
use App\Modules\Playlists\Helper\Settings\Orchestrator;
use App\Modules\Playlists\Helper\Settings\TemplatePreparer;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

class ShowSettingsControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private Messages&MockObject $flashMock;
	private ShowSettingsController $controller;

	/** @var array{UID: int, company_id: int, playlist_id: int, playlist_mode: string, playlist_name: string} */
	private array $playlist = ['UID' => 3, 'company_id' => 1, 'playlist_id' => 5, 'playlist_mode' => 'internal', 'playlist_name' => 'Lobby'];

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

		$this->controller = new ShowSettingsController($this->orchestratorMock, $this->templatePreparerMock, $this->flashMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testNewPlaylistFormWithDefaultMode(): void
	{
		$this->expectCreateRendering('master', []);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->newPlaylistForm($this->createStub(ServerRequestInterface::class), new Response(), []);

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testNewPlaylistForm(): void
	{
		$this->expectCreateRendering('multizone', []);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->newPlaylistForm($this->createStub(ServerRequestInterface::class), new Response(), ['playlist_mode' => 'multizone']);

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditPlaylistFormWithoutId(): void
	{
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'Playlist ID not valid.');

		$response = $this->controller->editPlaylistForm($this->createStub(ServerRequestInterface::class), new Response(), []);

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditPlaylistFormNotFound(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadPlaylistForEdit')->with(5)->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('buildEditForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'Playlist not found.');

		$response = $this->controller->editPlaylistForm($this->createStub(ServerRequestInterface::class), new Response(), ['playlist_id' => '5']);

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditPlaylistForm(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadPlaylistForEdit')->with(5)->willReturn($this->playlist);
		$this->expectEditRendering([]);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->editPlaylistForm($this->createStub(ServerRequestInterface::class), new Response(), ['playlist_id' => '5']);

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreInsertWithErrorsShowsFormAgain(): void
	{
		$post = ['playlist_name' => '', 'playlist_mode' => 'multizone'];
		$this->orchestratorMock->expects($this->never())->method('loadPlaylistForEdit');
		$this->orchestratorMock->expects($this->once())->method('storeCreateSettings')->with($post)
			->willReturn(['success' => false, 'errors' => ['No playlist name.', 'CSRF token mismatch.']]);
		$errors = [];
		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnCallback(function (string $key, string $message) use (&$errors): void { $errors[] = $key . ':' . $message; });
		$this->flashMock->expects($this->never())->method('addMessage');
		$this->expectCreateRendering('multizone', $post);

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRendered($response);
		static::assertSame(['error:No playlist name.', 'error:CSRF token mismatch.'], $errors);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreInsert(): void
	{
		$this->orchestratorMock->expects($this->once())->method('storeCreateSettings')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('buildCreateForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'Playlist “Lobby“ successfully stored.');

		$response = $this->controller->store($this->createRequest(['playlist_name' => 'Lobby', 'playlist_mode' => 'internal']), new Response());

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreUpdateNotEditable(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadPlaylistForEdit')->with(5)->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('storeEditSettings');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'Playlist not found.');

		$response = $this->controller->store($this->createRequest(['playlist_id' => '5', 'playlist_name' => 'Lobby']), new Response());

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreUpdateWithErrorsShowsFormAgain(): void
	{
		$post = ['playlist_id' => '5', 'playlist_name' => ''];
		$this->orchestratorMock->expects($this->once())->method('loadPlaylistForEdit')->with(5)->willReturn($this->playlist);
		$this->orchestratorMock->expects($this->once())->method('storeEditSettings')->with($post)
			->willReturn(['success' => false, 'errors' => ['No playlist name.']]);
		$this->flashMock->expects($this->once())->method('addMessageNow')->with('error', 'No playlist name.');
		$this->flashMock->expects($this->never())->method('addMessage');
		$this->expectEditRendering($post);

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreUpdate(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadPlaylistForEdit')->willReturn($this->playlist);
		$this->orchestratorMock->expects($this->once())->method('storeEditSettings')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('buildEditForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'Playlist “Lobby“ successfully stored.');

		$response = $this->controller->store($this->createRequest(['playlist_id' => '5', 'playlist_name' => 'Lobby']), new Response());

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function expectCreateRendering(string $playlistMode, array $post): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildCreateForm')->with($playlistMode, $post)->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepare')->with($this->form, $playlistMode)
			->willReturn($this->templateData);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function expectEditRendering(array $post): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildEditForm')->with($post)->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepare')->with($this->form, 'internal')
			->willReturn($this->templateData);
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

	private function assertRendered(ResponseInterface $response): void
	{
		static::assertSame(200, $response->getStatusCode());
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame(serialize($this->templateData), (string) $response->getBody());
	}

	private function assertRedirectedToPlaylists(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/playlists', $response->getHeaderLine('Location'));
	}
}
