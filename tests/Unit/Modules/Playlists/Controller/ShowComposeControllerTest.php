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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Controller\ShowComposeController;
use App\Modules\Playlists\Helper\Compose\UiTemplatesPreparer;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

class ShowComposeControllerTest extends TestCase
{
	private PlaylistsService&MockObject $playlistsServiceMock;
	private UiTemplatesPreparer&MockObject $uiTemplatesPreparerMock;
	private Messages&MockObject $flashMock;
	private ShowComposeController $controller;

	/** @var array<string,mixed> */
	private array $data = ['uiTemplates' => 'someData'];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playlistsServiceMock    = $this->createMock(PlaylistsService::class);
		$this->uiTemplatesPreparerMock = $this->createMock(UiTemplatesPreparer::class);
		$this->flashMock               = $this->createMock(Messages::class);

		$this->controller = new ShowComposeController(
			$this->playlistsServiceMock,
			$this->uiTemplatesPreparerMock,
			$this->flashMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShowRedirectsWhenPlaylistIdIsInvalid(): void
	{
		$this->uiTemplatesPreparerMock->expects($this->never())->method($this->anything());
		$this->playlistsServiceMock->expects($this->never())->method('loadPlaylistForEdit');
		$this->expectError('Playlist ID not valid.');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), []);

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShowRedirectsWhenPlaylistNotFound(): void
	{
		$this->uiTemplatesPreparerMock->expects($this->never())->method($this->anything());
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')->with(1)
			->willThrowException(new NotFoundException('playlists', 'playlist_not_found'));
		$this->expectError('Playlist not found.');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['playlist_id' => 1]);

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShowRedirectsWhenPlaylistNotEditable(): void
	{
		$this->uiTemplatesPreparerMock->expects($this->never())->method($this->anything());
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')->with(1)
			->willThrowException(new AccessDeniedException('playlists', 'playlist_not_editable'));
		$this->expectError('Playlist is not editable.');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['playlist_id' => 1]);

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @return array<string, array{string, non-empty-string}>
	 */
	public static function provideEditors(): array
	{
		return [
			'multizone' => ['multizone', 'buildMultizoneEditor'],
			'external'  => ['external', 'buildExternalEditor'],
			'channel'   => ['channel', 'buildCircularEditor'],
			'internal'  => ['internal', 'buildCircularEditor'],
			'master'    => ['master', 'buildCircularEditor'],
		];
	}

	/**
	 * @param non-empty-string $builderMethod
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[DataProvider('provideEditors')] #[Group('units')]
	public function testShowBuildsEditorForPlaylistMode(string $playlistMode, string $builderMethod): void
	{
		$playlist = ['playlist_mode' => $playlistMode];
		$this->expectPlaylistLoaded($playlist);
		$this->uiTemplatesPreparerMock->expects($this->once())->method($builderMethod)
			->with($playlist)->willReturn($this->data);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['playlist_id' => 1]);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame(serialize($this->data), (string) $response->getBody());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShowRedirectsForUnsupportedMode(): void
	{
		$this->uiTemplatesPreparerMock->expects($this->never())->method($this->anything());
		$this->expectPlaylistLoaded(['playlist_mode' => 'unsupported']);
		$this->expectError('Unsupported playlist mode: unsupported');

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response(), ['playlist_id' => 1]);

		$this->assertRedirectedToPlaylists($response);
	}

	/**
	 * @param array<string,mixed> $playlist
	 */
	private function expectPlaylistLoaded(array $playlist): void
	{
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')->with(1)->willReturn($playlist);
	}

	private function expectError(string $message): void
	{
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', $message);
	}

	private function assertRedirectedToPlaylists(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/playlists', $response->getHeaderLine('Location'));
	}
}
