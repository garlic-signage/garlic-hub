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
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Playlists\Controller\ShowSettingsController;
use App\Modules\Playlists\Helper\Settings\Facade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowSettingsControllerTest extends TestCase
{
	private Facade&MockObject $facadeMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private StreamInterface&Stub $streamInterfaceStub;
	private FormTemplatePreparer&MockObject $formElementPreparerMock;
	private Messages&MockObject $flashMock;
	private ShowSettingsController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->facadeMock              = $this->createMock(Facade::class);
		$this->requestMock             = $this->createMock(ServerRequestInterface::class);
		$this->responseMock            = $this->createMock(ResponseInterface::class);
		$this->streamInterfaceStub     = $this->createStub(StreamInterface::class);
		$this->flashMock               = $this->createMock(Messages::class);
		$this->formElementPreparerMock = $this->createMock(FormTemplatePreparer::class);

		$this->controller = new ShowSettingsController($this->facadeMock, $this->formElementPreparerMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testNewPlaylistFormWithDefaultMode(): void
	{
		$this->flashMock->expects($this->never())->method($this->anything());
		$args = []; // No playlist_mode provided means master

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('buildCreateNewParameter')
			->with('master');

		$dataSections = ['data_sections'];
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with(['playlist_mode' => 'master'])
			->willReturn($dataSections);

		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')
			->with($dataSections)
			->willReturn(['rendered_template' => 'example']);

		$this->responseMock->expects($this->once())->method('getBody')
			->willReturn($this->createStub(StreamInterface::class));

		$this->responseMock	->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->controller->newPlaylistForm($this->requestMock, $this->responseMock, $args);

	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testNewPlaylistForm(): void
	{
		$this->flashMock->expects($this->never())->method($this->anything());
		$args = ['playlist_mode' => 'channel'];

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('buildCreateNewParameter')
			->with('channel');

		$dataSections = ['data_sections'];
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with(['playlist_mode' => 'channel'])
			->willReturn($dataSections);

		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')
			->with($dataSections)
			->willReturn(['rendered_template' => 'example']);

		$this->responseMock->expects($this->once())->method('getBody')
			->willReturn($this->createStub(StreamInterface::class));

		$this->responseMock	->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->controller->newPlaylistForm($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testEditPlaylistFormWithInvalidPlaylistId(): void
	{
		$this->formElementPreparerMock->expects($this->never())->method($this->anything());

		$this->setStandardMocks();

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('error', 'Playlist ID not valid.');


		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/playlists')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$this->facadeMock->expects($this->never())->method('loadPlaylistForEdit')
			->with(1)
			->willReturn([]);

		$this->controller->editPlaylistForm($this->requestMock, $this->responseMock, []);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testEditPlaylistFormWithNonExistentPlaylist(): void
	{
		$this->formElementPreparerMock->expects($this->never())->method($this->anything());
		$args = ['playlist_id' => '1', 'playlist_mode' => 'master'];

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('loadPlaylistForEdit')
			->with(1)
			->willReturn([]);

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('error', 'Playlist not found.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/playlists')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$this->controller->editPlaylistForm($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testEditPlaylistFormWithValidPlaylistId(): void
	{
		// the form is rendered again
		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')->willReturn(['main_layout' => [], 'this_layout' => []]);
		$this->flashMock->expects($this->never())->method($this->anything());
		$args = ['playlist_id' => '1'];
		$playlist = ['id' => 1, 'name' => 'Sample Playlist'];

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('loadPlaylistForEdit')
			->with(1)
			->willReturn($playlist);

		$this->facadeMock->expects($this->once())->method('buildEditParameter')
			->with($playlist);

		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with($playlist)
			->willReturn(['rendered_template' => 'example']);

		$this->responseMock->expects($this->once())->method('getBody')
			->willReturn($this->createStub(StreamInterface::class));

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->controller->editPlaylistForm($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException|Exception
	 */
	#[Group('units')]
	public function testStorePlaylistSuccessfully(): void
	{
		$this->formElementPreparerMock->expects($this->never())->method($this->anything());
		$post = ['playlist_name' => 'Test Playlist'];
		$this->setStandardMocks();

		$this->requestMock->expects($this->once())->method('getParsedBody')->willReturn($post);

		$this->facadeMock->expects($this->once())->method('configurePlaylistFormParameter')
			->with($post)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storePlaylist')
			->with($post)
			->willReturn(123);

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'Playlist “Test Playlist“ successfully stored.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/playlists')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStorePlaylistWithErrorMessages(): void
	{
		// the form is rendered again
		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')->willReturn(['main_layout' => [], 'this_layout' => []]);
		$post = ['playlist_name' => 'Test Playlist'];
		$errors = ['Error 1', 'Error 2'];
		$this->setStandardMocks();

		$this->requestMock->expects($this->once())->method('getParsedBody')->willReturn($post);

		$this->facadeMock->expects($this->once())->method('configurePlaylistFormParameter')
			->with($post)
			->willReturn($errors);

		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnMap([
				['error', 'Error 1'],
				['error', 'Error 2']
			]
			);

		$this->facadeMock->expects($this->never())->method('storePlaylist');

		$this->responseMock->expects($this->once())->method('getBody')->willReturn($this->streamInterfaceStub);

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStorePlaylistWithInvalidPostData(): void
	{
		// the form is rendered again
		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')->willReturn(['main_layout' => [], 'this_layout' => []]);
		$post = [];
		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($post);

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('configurePlaylistFormParameter')
			->with([])
			->willReturn(['Invalid data']);

		$this->flashMock->expects($this->once())->method('addMessageNow')
			->with('error', 'Invalid data');

		$this->facadeMock->expects($this->never())->method('storePlaylist');

		$this->responseMock->expects($this->once())->method('getBody')->willReturn($this->streamInterfaceStub);

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->controller->store($this->requestMock, $this->responseMock);

	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStorePlaylistFailsStore(): void
	{
		// the form is rendered again
		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')->willReturn(['main_layout' => [], 'this_layout' => []]);
		$this->flashMock->expects($this->never())->method($this->anything());
		$post = ['playlist_name' => 'Test Playlist'];
		$this->setStandardMocks();

		$this->requestMock->expects($this->once())->method('getParsedBody')->willReturn($post);

		$this->facadeMock->expects($this->once())->method('configurePlaylistFormParameter')
			->with($post)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storePlaylist')
			->with($post)
			->willReturn(0);

		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with($post)
			->willReturn(['rendered_template' => 'example']);

		$this->responseMock->expects($this->once())->method('getBody')->willReturn($this->streamInterfaceStub);

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	private function setStandardMocks(): void
	{
		$translatorStub = $this->createStub(Translator::class);
		$sessionStub = $this->createStub(Session::class);
		$this->requestMock->expects($this->exactly(3))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $translatorStub],
				['session', null, $sessionStub]
			]);

		$this->facadeMock->expects($this->once())->method('init')
			->with($translatorStub, $sessionStub);

	}
}
