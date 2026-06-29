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

use App\Framework\Core\CsrfToken;
use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Controller\PlaylistsController;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use App\Modules\Playlists\Services\PlaylistsService;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class PlaylistControllerTest extends TestCase
{
	private PlaylistsController $controller;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private PlaylistsDatatableService&MockObject $playlistsDatatableServiceMock;
	private Parameters&MockObject $parametersMock;
	private ResponseInterface&MockObject $responseMock;
	private ServerRequestInterface&MockObject $requestMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private Session&MockObject $sessionMock;
	private CsrfToken&MockObject $csrfTokenMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playlistsServiceMock = $this->createMock(PlaylistsService::class);
		$this->playlistsDatatableServiceMock = $this->createMock(PlaylistsDatatableService::class);
		$this->parametersMock       = $this->createMock(Parameters::class);
		$this->requestMock          = $this->createMock(ServerRequestInterface::class);
		$this->responseMock         = $this->createMock(ResponseInterface::class);
		$this->streamInterfaceMock  = $this->createMock(StreamInterface::class);
		$this->sessionMock          = $this->createMock(Session::class);
		$this->csrfTokenMock    = $this->createMock(CsrfToken::class);

		$this->controller = new PlaylistsController($this->playlistsServiceMock, $this->playlistsDatatableServiceMock,$this->parametersMock, $this->csrfTokenMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$post =  ['playlist_id' => 789];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$this->playlistsServiceMock->method('delete')->with(789)->willReturn(1);
		$this->mockJsonResponse(['success' => true]);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteInvalidCsrf(): void
	{
		$post =  [];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(false);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'CSRF token mismatch.']);
		$this->playlistsServiceMock->expects($this->never())->method('delete');

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeleteInvalidPlaylistId(): void
	{
		$post =  [];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist ID not valid.']);
		$this->playlistsServiceMock->expects($this->never())->method('delete');

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteNotFoundPlaylist(): void
	{
		$post =  ['playlist_id' => 12];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$this->playlistsServiceMock->method('delete')->with(12)->willReturn(0);
		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist not found.']);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testToggleShuffle(): void
	{
		$post =  ['playlist_id' => 12];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$data = ['affected' => 1, 'playlist_metrics' => ['some_metrics_array']];
		$this->playlistsServiceMock->method('toggleShuffle')->with(12)->willReturn($data);

		$this->mockJsonResponse(['success' => true, 'playlist_metrics' => $data['playlist_metrics']]);

		$this->controller->toggleShuffle($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testToggleShuffleInvalidCsrf(): void
	{
		$post =  [];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(false);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'CSRF token mismatch.']);
		$this->playlistsServiceMock->expects($this->never())->method('toggleShuffle');

		$this->controller->toggleShuffle($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testToggleShuffleInvalidPlaylistId(): void
	{
		$post =  [];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist ID not valid.']);
		$this->playlistsServiceMock->expects($this->never())->method('toggleShuffle');

		$this->controller->toggleShuffle($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testToggleShuffleWhenNotEffecting(): void
	{
		$post =  ['playlist_id' => 12];

		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$data = ['affected' => 0, 'playlist_metrics' => ['some_metrics_array']];
		$this->playlistsServiceMock->method('toggleShuffle')->with(12)->willReturn($data);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist not found.']);

		$this->controller->toggleShuffle($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShufflePicking(): void
	{
		$post =  ['playlist_id' => 11, 'shuffle_picking' => 4];

		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$data = ['affected' => 1, 'playlist_metrics' => ['some_metrics_array']];
		$this->playlistsServiceMock->method('shufflePicking')->with(11, 4)->willReturn($data);

		$this->mockJsonResponse(['success' => true, 'playlist_metrics' => $data['playlist_metrics']]);

		$this->controller->shufflePicking($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShufflePickingInvalidCsrf(): void
	{
		$post =  ['playlist_id' => 11];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(false);

		$this->playlistsServiceMock->expects($this->never())->method('shufflePicking');
		$this->mockJsonResponse(['success' => false, 'error_message' => 'CSRF token mismatch.']);

		$this->controller->shufflePicking($this->requestMock, $this->responseMock);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShufflePickingInvalidPlaylistId(): void
	{
		$post =  ['playlist_id' => 11];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->playlistsServiceMock->expects($this->never())->method('shufflePicking');
		$this->mockJsonResponse(['success' => false, 'error_message' => 'No picking value found.']);

		$this->controller->shufflePicking($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShufflePickingInvalidPicking(): void
	{
		$post =  ['shuffle_picking' => 4];
		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->playlistsServiceMock->expects($this->never())->method('shufflePicking');
		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->controller->shufflePicking($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShufflePickingPlaylistNotFound(): void
	{
		$post =  ['playlist_id' => 11, 'shuffle_picking' => 4];

		$this->requestMock->method('getParsedBody')->willReturn($post);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsServiceMock->method('setUID')->with(456);

		$data = ['affected' => 0, 'playlist_metrics' => ['some_metrics_array']];
		$this->playlistsServiceMock->method('shufflePicking')->with(11, 4)->willReturn($data);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist not found.']);

		$this->controller->shufflePicking($this->requestMock, $this->responseMock);
	}

	#[Group('units')]
	public function testLoadZoneSucceed(): void
	{
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);

		$this->playlistsServiceMock->expects($this->once())->method('setUID')->with(456);
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForMultizone')
			->with(14)->willReturn(['some_zone_stuff']);

		$this->mockJsonResponse(['success' => true, 'zones' => ['some_zone_stuff']]);

		$this->playlistsServiceMock->expects($this->once())->method('hasErrorMessages')->willReturn(false);
		$this->playlistsServiceMock->expects($this->never())->method('getErrorMessages');

		$this->controller->loadZone($this->requestMock, $this->responseMock, ['playlist_id' => 14]);
	}

	#[Group('units')]
	public function testLoadZoneInvalidPlaylistId(): void
	{
		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->playlistsServiceMock->expects($this->never())->method('setUID');
		$this->playlistsServiceMock->expects($this->never())->method('loadPlaylistForMultizone');

		$this->controller->loadZone($this->requestMock, $this->responseMock, ['playlist_id' => 0]);
	}

	#[Group('units')]
	public function testLoadZoneErrors(): void
	{
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);

		$this->playlistsServiceMock->expects($this->once())->method('setUID')->with(456);
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForMultizone')
			->with(14)->willReturn(['some_zone_stuff']);

		$this->playlistsServiceMock->method('hasErrorMessages')->willReturn(true);

		$this->playlistsServiceMock->expects($this->once())->method('getErrorMessages')->willReturn( ['errors']);
		$this->mockJsonResponse(['success' => false, 'error_message' => ['errors']]);

		$this->controller->loadZone($this->requestMock, $this->responseMock, ['playlist_id' => 14]);
	}

	#[Group('units')]
	public function testSaveZoneSucceed(): void
	{
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);

		$this->playlistsServiceMock->expects($this->once())->method('setUID')->with(456);

		$this->requestMock->method('getParsedBody')->willReturn(['save_zone_stuff']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->playlistsServiceMock->expects($this->once())->method('saveZones')
			->with(14, ['save_zone_stuff'])->willReturn(1);

		$this->mockJsonResponse(['success' => true]);

		$this->controller->saveZone($this->requestMock, $this->responseMock, ['playlist_id' => 14]);
	}


	#[Group('units')]
	public function testSaveZoneInvalidPlaylistId(): void
	{
		$this->playlistsServiceMock->expects($this->never())->method('setUID');
		$this->requestMock->expects($this->never())->method('getParsedBody');
		$this->playlistsServiceMock->expects($this->never())->method('loadPlaylistForMultizone');

		$this->playlistsServiceMock->expects($this->never())->method('saveZones');
		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist ID not valid.']);


		$this->controller->saveZone($this->requestMock, $this->responseMock, ['playlist_id' => 0]);
	}

	#[Group('units')]
	public function testSaveZoneInvalidCsrf(): void
	{
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);

		$this->playlistsServiceMock->expects($this->once())->method('setUID')->with(456);

		$this->requestMock->method('getParsedBody')->willReturn(['save_zone_stuff']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(false);
		$this->playlistsServiceMock->expects($this->never())->method('saveZones');

		$this->mockJsonResponse(['success' => false, 'error_message' => 'CSRF token mismatch.']);

		$this->controller->saveZone($this->requestMock, $this->responseMock, ['playlist_id' => 14]);
	}
	#[Group('units')]
	public function testNotSaveZone(): void
	{
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);

		$this->playlistsServiceMock->expects($this->once())->method('setUID')->with(456);

		$this->requestMock->method('getParsedBody')->willReturn(['save_zone_stuff']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->playlistsServiceMock->expects($this->once())->method('saveZones')
			->with(14, ['save_zone_stuff'])->willReturn(0);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Multizone could not be saved']);

		$this->controller->saveZone($this->requestMock, $this->responseMock, ['playlist_id' => 14]);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindByName(): void
	{
		$input = [
			'name' => 'play',
			'elements_per_page' => 0,
			'sort_column' => 'playlist_id',
			'sort_order' => 'DESC'
		];

		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsDatatableServiceMock->expects($this->once())->method('setUID')->with(456);

		$this->playlistsDatatableServiceMock->expects($this->once())->method('loadDatatable');
		$playlists = [
			['playlist_id' => 1, 'playlist_name' => 'playlist1', 'description' => 'description1'],
			['playlist_id' => 2, 'playlist_name' => 'playlist2', 'description' => 'description2'],
			['playlist_id' => 3, 'playlist_name' => 'playlist3', 'description' => 'description3'],
			['playlist_id' => 4, 'playlist_name' => 'playlist4', 'description' => 'description4'],
		];

		$this->playlistsDatatableServiceMock->expects($this->once())->method('getCurrentFilterResults')->willReturn($playlists);

		$output = [
			['id' => 1, 'name' => 'playlist1'],
			['id' => 2, 'name' => 'playlist2'],
			['id' => 3, 'name' => 'playlist3'],
			['id' => 4, 'name' => 'playlist4']
		];

		$this->mockJsonResponse($output);

		$this->controller->findByName($this->requestMock, $this->responseMock, $input);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindForPlayerAssignment(): void
	{
		$arg =  ['playlist_id' => 11];
		$args = [
			'playlist_id' => 11,
			'playlist_mode' => 'master,multizone',
			'elements_per_page' => 0,
			'sort_column' => 'playlist_id',
			'sort_order' => 'DESC'
		];

		$this->parametersMock->expects($this->once())->method('setUserInputs')->with($args);
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsDatatableServiceMock->expects($this->once())->method('setUID')->with(456);

		$this->playlistsDatatableServiceMock->expects($this->once())->method('loadDatatable');
		$playlists = [
			['playlist_id' => 1, 'playlist_name' => 'playlist1', 'description' => 'description1'],
			['playlist_id' => 2, 'playlist_name' => 'playlist2', 'description' => 'description2']
		];

		$this->playlistsDatatableServiceMock->expects($this->once())->method('getCurrentFilterResults')->willReturn($playlists);

		$output = [
			['id' => 1, 'name' => 'playlist1'],
			['id' => 2, 'name' => 'playlist2']
		];

		$this->mockJsonResponse($output);

		$this->controller->findForPlayerAssignment($this->requestMock, $this->responseMock, $arg);

	}


	#[Group('units')]
	public function testFindById(): void
	{
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 456]);
		$this->playlistsServiceMock->expects($this->once())->method('setUID')->with(456);

		$this->playlistsServiceMock->expects($this->once())->method('loadNameById')
			->with(14)->willReturn(['playlist_name']);

		$this->mockJsonResponse(['playlist_name']);

		$this->controller->findById($this->requestMock, $this->responseMock, ['playlist_id' => 14]);
	}

	#[Group('units')]
	public function testFindByIdFail(): void
	{
		$this->playlistsServiceMock->expects($this->never())->method('loadNameById');

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->controller->findById($this->requestMock, $this->responseMock, []);
	}


	/**
	 * @param array<string,mixed>|list<array<string,mixed>> $data
	 */
	private function mockJsonResponse(array $data): void
	{
		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->method('write')->with(json_encode($data));
		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'application/json')
			->willReturnSelf();
		$this->responseMock->method('withStatus')->with('200');
	}
}
