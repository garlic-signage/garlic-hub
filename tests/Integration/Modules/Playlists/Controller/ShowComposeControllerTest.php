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

namespace Tests\Integration\Modules\Playlists\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Playlists\Controller\ShowComposeController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The editor of a playlist /playlists/compose/{id}: the editor depends on the mode of the playlist, a playlist which
 * the user may not edit sends him back to the list with a message.
 */
#[Group('acl')]
class ShowComposeControllerTest extends PlaylistsControllerTestCase
{
	private ShowComposeController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(ShowComposeController::class);
	}

	private function show(string $playlistId = ''): ResponseInterface
	{
		return $this->controller->show($this->request('GET'), $this->newResponse(), $playlistId === '' ? [] : ['playlist_id' => $playlistId]);
	}

	private function page(string $playlistId): PageResponse
	{
		$page = $this->show($playlistId);
		static::assertInstanceOf(PageResponse::class, $page);

		return $page;
	}

	/**
	 * @return list<string>
	 */
	private function flash(string $type): array
	{
		/** @var array<string,list<string>> $next */
		$next = $_SESSION['slimFlash'] ?? [];

		return $next[$type] ?? [];
	}

	private function assertRedirect(ResponseInterface $response, string $error): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/playlists', $response->getHeaderLine('Location'));
		static::assertSame([$error], $this->flash('error'));
	}

	/**
	 * @return array<string,array{string,string}> the mode of the playlist and the template of the editor
	 */
	public static function editors(): array
	{
		return [
			'master'    => ['master', 'playlists/compose'],
			'internal'  => ['internal', 'playlists/compose'],
			'channel'   => ['channel', 'playlists/compose'],
			'multizone' => ['multizone', 'playlists/multizone'],
			'external'  => ['external', 'playlists/external'],
		];
	}

	#[DataProvider('editors')]
	public function testTheEditorDependsOnTheModeOfThePlaylist(string $mode, string $template): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (4, 2, '" . $mode . "', 'Test')");

		$page = $this->show('4');

		static::assertInstanceOf(PageResponse::class, $page);
		static::assertSame($template, $page->getTemplate());
		static::assertStringContainsString('(Test)', $page->getTemplateData()['LANG_PAGE_HEADER']);
	}

	public function testTheComposerKnowsThePlaylistAndWhatMayBeInserted(): void
	{
		$this->actingAs(2);

		$data = $this->page('1')->getTemplateData();

		static::assertSame(1, $data['PLAYLIST_ID']);
		static::assertSame('edge', $data['CMS_EDITION']);
		// a playlist without a time limit can contain playlists and templates, the edge edition no external media
		static::assertNotSame([], $data['can_playlists']);
		static::assertNotSame([], $data['can_templates']);
		static::assertSame([], $data['can_external_media']);
		static::assertSame([], $data['has_time_limit']);
	}

	public function testAPlaylistWithATimeLimitCanNotContainPlaylists(): void
	{
		$this->actingAs(3);

		$data = $this->page('2')->getTemplateData();

		static::assertSame([], $data['can_playlists']);
		static::assertNotSame([], $data['has_time_limit']);
	}

	public function testAUserSendsBackWhoMayNotEditThePlaylist(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->show('2'), 'Playlist is not editable.');
	}

	public function testAnUnknownPlaylistOrNoPlaylistSendsBackToTheList(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->show('999'), 'Playlist not found.');
	}

	public function testWithoutAPlaylistIdTheUserIsSentBack(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->show(), 'Playlist ID not valid.');
	}

	public function testTheAdminMayEditEveryPlaylist(): void
	{
		$this->actingAs(1);

		static::assertInstanceOf(PageResponse::class, $this->show('2'));
	}
}
