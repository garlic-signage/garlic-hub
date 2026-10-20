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

namespace Tests\Unit\Modules\Playlists\Controller;

use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Playlists\Controller\ShowComposeController;
use App\Modules\Playlists\Helper\Compose\UiTemplatesPreparer;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowComposeControllerTest extends TestCase
{
	private ResponseInterface&MockObject $responseMock;
	private ServerRequestInterface&MockObject $requestMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private UiTemplatesPreparer&MockObject $uiTemplatesPreparerMock;
	private Messages&MockObject $flashMock;
	private ShowComposeController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestMock             = $this->createMock(ServerRequestInterface::class);
		$this->responseMock            = $this->createMock(ResponseInterface::class);
		$this->streamInterfaceMock     = $this->createMock(StreamInterface::class);
		$sessionMock = $this->createMock(Session::class);
		$this->playlistsServiceMock    = $this->createMock(PlaylistsService::class);
		$this->uiTemplatesPreparerMock = $this->createMock(UiTemplatesPreparer::class);
		$this->flashMock               = $this->createMock(Messages::class);

		$this->requestMock->method('getAttribute')->willReturnMap([
			['session', $sessionMock],
			['flash', $this->flashMock]
		]);
		$sessionMock->method('get')->willReturn(['UID' => 123]);
		$this->playlistsServiceMock->expects($this->once())->method('setUID')->with(123);

		$this->controller = new ShowComposeController($this->playlistsServiceMock, $this->uiTemplatesPreparerMock);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowRedirectsWhenPlaylistIdIsInvalid(): void
	{
		$this->outputSimpleErrorMock('Playlist ID not valid.');
		$this->playlistsServiceMock->expects($this->never())->method('loadPlaylistForEdit');

		$this->controller->show($this->requestMock, $this->responseMock, []);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowRedirectsWhenPlaylistIsEmpty(): void
	{
		$this->playlistsServiceMock->method('loadPlaylistForEdit')->willReturn([]);

		$this->outputSimpleErrorMock('Playlist not found.');

		$this->controller->show($this->requestMock, $this->responseMock,  ['playlist_id' => 1]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowCallsBuildMultizoneEditorForMultizoneMode(): void
	{
		$playlist = ['playlist_mode' => 'multizone'];
		$this->playlistsServiceMock->method('loadPlaylistForEdit')->willReturn($playlist);
		$data = ['uiTemplates' => 'someData'];
		$this->uiTemplatesPreparerMock->expects($this->once())->method('buildMultizoneEditor')
			->with($playlist)
			->willReturn($data);

		$this->outputStandard($data);
		$this->controller->show($this->requestMock, $this->responseMock, ['playlist_id' => 1]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowCallsBuildMultizoneEditorForExternalMode(): void
	{
		$playlist = ['playlist_mode' => 'external'];
		$this->playlistsServiceMock->method('loadPlaylistForEdit')->willReturn($playlist);
		$data = ['uiTemplates' => 'someData'];
		$this->uiTemplatesPreparerMock->expects($this->once())->method('buildExternalEditor')
			->with($playlist)
			->willReturn($data);

		$this->outputStandard($data);
		$this->controller->show($this->requestMock, $this->responseMock, ['playlist_id' => 1]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowCallsBuildMultizoneEditorForStandards(): void
	{
		$playlist = ['playlist_mode' => 'master'];
		$this->playlistsServiceMock->method('loadPlaylistForEdit')->willReturn($playlist);
		$data = ['uiTemplates' => 'someData'];
		$this->uiTemplatesPreparerMock->expects($this->once())->method('buildCircularEditor')
			->with($playlist)
			->willReturn($data);

		$this->outputStandard($data);
		$this->controller->show($this->requestMock, $this->responseMock, ['playlist_id' => 1]);
	}


	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowCallsUnsupported(): void
	{
		$playlist = ['playlist_mode' => 'unsupported'];
		$this->playlistsServiceMock->method('loadPlaylistForEdit')->willReturn($playlist);

		$this->outputSimpleErrorMock('Unsupported playlist mode: .'.$playlist['playlist_mode']);
		$this->controller->show($this->requestMock, $this->responseMock, ['playlist_id' => 1]);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowFlashErrors(): void
	{
		$this->playlistsServiceMock->method('hasErrorMessages')->willReturn(true);
		$this->playlistsServiceMock->method('getErrorMessages')->willReturn(['error1', 'error2']);

		$this->flashMock->expects($this->exactly(2))->method('addMessage')
			->willReturnMap([
				['error', 'error1'],
				['error', 'error2']
			]);

		$this->responseMock->method('withHeader')->with('Location', '/playlists')->willReturnSelf();
		$this->responseMock->method('withStatus')->with('302');

		$this->controller->show($this->requestMock, $this->responseMock, []);
	}

	/**
	 * @throws Exception
	 */
	private function outputSimpleErrorMock(string $errorMessage): void
	{
		$this->playlistsServiceMock->method('hasErrorMessages')->willReturn(false);
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', $errorMessage);

		$this->responseMock->method('withHeader')->with('Location', '/playlists')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(302);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function outputStandard(array $data): void
	{
		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->method('write')->with(serialize($data));
		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(200);
	}


}
