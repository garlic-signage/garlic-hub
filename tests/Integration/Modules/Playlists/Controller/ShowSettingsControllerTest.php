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
use App\Modules\Playlists\Controller\ShowSettingsController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Flash\Messages;

/**
 * The settings of a playlist /playlists/settings: the form for a new and for an existing playlist and storing it.
 * The controller keeps the input of the request, so every test makes one request.
 */
#[Group('acl')]
class ShowSettingsControllerTest extends PlaylistsControllerTestCase
{
	private ShowSettingsController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(ShowSettingsController::class);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function store(array $body): ResponseInterface
	{
		return $this->controller->store($this->request(body: $body), $this->newResponse());
	}

	private function page(ResponseInterface $response): PageResponse
	{
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	/**
	 * @return list<string> the names of the fields of the form
	 */
	private function fields(PageResponse $page): array
	{
		$data  = $page->getTemplateData();
		$names = [];
		foreach (array_merge(array_column($data['form_element'], 'HTML_ELEMENT'), array_column($data['element_hidden'], 'HIDDEN_HTML_ELEMENT')) as $html)
		{
			if (preg_match('/name="([^"]+)"/', $html, $match) === 1)
				$names[] = $match[1];
		}
		sort($names);

		return $names;
	}

	/**
	 * @return list<string> the messages for the next request (a redirect) and for this one (a form which is shown again)
	 */
	private function flash(string $type): array
	{
		/** @var array<string,list<string>> $next */
		$next = $_SESSION['slimFlash'] ?? [];

		return array_values(array_merge($next[$type] ?? [], $this->container->get(Messages::class)->getMessage($type) ?? []));
	}

	private function assertRedirect(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/playlists', $response->getHeaderLine('Location'));
	}

	public function testTheFormOfANewMasterPlaylistHasTheResolution(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->controller->newPlaylistForm($this->request('GET'), $this->newResponse(), ['playlist_mode' => 'master']));

		static::assertSame('playlists/edit', $page->getTemplate());
		static::assertSame(['csrf_token', 'playlist_mode', 'playlist_name', 'screen_height', 'screen_width'], $this->fields($page));
	}

	public function testTheFormOfANewInternalPlaylistHasNoResolution(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->controller->newPlaylistForm($this->request('GET'), $this->newResponse(), ['playlist_mode' => 'internal']));

		static::assertSame(['csrf_token', 'playlist_mode', 'playlist_name'], $this->fields($page));
	}

	public function testOnlyTheAdminCanChooseTheOwner(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->controller->newPlaylistForm($this->request('GET'), $this->newResponse(), ['playlist_mode' => 'internal']));

		static::assertContains('UID', $this->fields($page));
	}

	public function testTheFormOfAPlaylistHasItsValues(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->controller->editPlaylistForm($this->request('GET'), $this->newResponse(), ['playlist_id' => '1']));

		static::assertSame(['csrf_token', 'playlist_id', 'playlist_name', 'screen_height', 'screen_width'], $this->fields($page));
		static::assertStringContainsString('Annas Lobby', json_encode($page->getTemplateData()['form_element'], JSON_THROW_ON_ERROR));
	}

	public function testThePlaylistOfAnotherUserHasNoForm(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->controller->editPlaylistForm($this->request('GET'), $this->newResponse(), ['playlist_id' => '2']));

		static::assertSame(['Playlist not found.'], $this->flash('error'));
	}

	public function testAnUnknownPlaylistOrNoPlaylistHasNoForm(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->controller->editPlaylistForm($this->request('GET'), $this->newResponse(), []));

		static::assertSame(['Playlist ID not valid.'], $this->flash('error'));
	}

	public function testANewPlaylistBelongsToTheUser(): void
	{
		$this->actingAs(2);

		$response = $this->store(['playlist_mode' => 'master', 'playlist_name' => 'Neu', 'screen_width' => '1280', 'screen_height' => '720']);

		$this->assertRedirect($response);
		static::assertSame(['Playlist “Neu“ successfully stored.'], $this->flash('success'));
		$row = $this->connection->fetchAssociative("SELECT UID, playlist_mode, layout FROM playlists WHERE playlist_name = 'Neu'");
		static::assertIsArray($row);
		static::assertSame([2, 'master'], [(int) $row['UID'], $row['playlist_mode']]);
		static::assertSame('1280x720', unserialize((string) $row['layout'])['resolution']);
	}

	public function testANewMultizonePlaylistGetsACompleteLayout(): void
	{
		$this->actingAs(2);

		$this->store(['playlist_mode' => 'multizone', 'playlist_name' => 'MZ', 'screen_width' => '1920', 'screen_height' => '1080']);

		static::assertSame(
			['resolution' => '1920x1080', 'zoom' => 70, 'export_unit' => 'pixel', 'zones' => []],
			unserialize((string) $this->databaseValue("SELECT layout FROM playlists WHERE playlist_name = 'MZ'"))
		);
	}

	/**
	 * A user can not make a playlist of somebody else or set a time limit by adding the fields to the request.
	 */
	public function testAUserCanNotSetTheOwnerOrTheTimeLimitOfANewPlaylist(): void
	{
		$this->actingAs(2);

		$this->store(['playlist_mode' => 'internal', 'playlist_name' => 'Neu', 'UID' => '3', 'time_limit' => '5']);

		static::assertSame([2, 0], array_map('intval', (array) $this->connection->fetchNumeric("SELECT UID, time_limit FROM playlists WHERE playlist_name = 'Neu'")));
	}

	public function testTheAdminCanChooseTheOwnerOfANewPlaylist(): void
	{
		$this->actingAs(1);

		$this->store(['playlist_mode' => 'internal', 'playlist_name' => 'Neu', 'UID' => '3']);

		static::assertSame(3, (int) $this->databaseValue("SELECT UID FROM playlists WHERE playlist_name = 'Neu'"));
	}

	public function testANewPlaylistNeedsANameAndAKnownMode(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->store(['playlist_mode' => 'master']));

		static::assertSame('playlists/edit', $page->getTemplate());
		static::assertCount(1, $this->flash('error'));
		static::assertSame(3, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
	}

	public function testAnUnknownModeIsNotStored(): void
	{
		$this->actingAs(2);

		$this->page($this->store(['playlist_mode' => 'evil', 'playlist_name' => 'X']));

		static::assertCount(1, $this->flash('error'));
		static::assertSame(3, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
	}

	public function testThePlaylistIsChangedButNotItsOwnerModeOrTimeLimit(): void
	{
		$this->actingAs(2);

		$response = $this->store(['playlist_id' => '1', 'playlist_name' => 'Umbenannt', 'screen_width' => '800', 'screen_height' => '600', 'UID' => '3', 'time_limit' => '5', 'playlist_mode' => 'internal']);

		$this->assertRedirect($response);
		static::assertSame(['Playlist “Umbenannt“ successfully stored.'], $this->flash('success'));
		$row = $this->connection->fetchAssociative('SELECT UID, time_limit, playlist_mode, playlist_name, layout FROM playlists WHERE playlist_id = 1');
		static::assertIsArray($row);
		static::assertSame([2, 0, 'master', 'Umbenannt'], [(int) $row['UID'], (int) $row['time_limit'], $row['playlist_mode'], $row['playlist_name']]);
		static::assertSame('800x600', unserialize((string) $row['layout'])['resolution']);
	}

	public function testTheAdminCanChangeTheOwner(): void
	{
		$this->actingAs(1);

		$this->store(['playlist_id' => '1', 'playlist_name' => 'Annas Lobby', 'UID' => '3']);

		static::assertSame(3, (int) $this->databaseValue('SELECT UID FROM playlists WHERE playlist_id = 1'));
	}

	public function testTheSettingsOfAnotherUsersPlaylistAreNotStored(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->store(['playlist_id' => '2', 'playlist_name' => 'Hack']));

		static::assertSame(['Playlist not found.'], $this->flash('error'));
		static::assertSame('Bernds Kantine', $this->databaseValue('SELECT playlist_name FROM playlists WHERE playlist_id = 2'));
	}

	/**
	 * An array as playlist id is not the playlist 1: the request is one for a new playlist.
	 */
	public function testAPlaylistIdOfTheWrongTypeIsNotAnExistingPlaylist(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->store(['playlist_id' => ['1'], 'playlist_name' => 'Hack']));

		static::assertSame('playlists/edit', $page->getTemplate());
		static::assertCount(1, $this->flash('error'));
		static::assertSame('Annas Lobby', $this->databaseValue('SELECT playlist_name FROM playlists WHERE playlist_id = 1'));
		static::assertSame(3, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
	}

	public function testWithoutABodyTheFormOfANewPlaylistIsShownWithErrors(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->controller->store($this->request()->withParsedBody(null), $this->newResponse()));

		static::assertSame('playlists/edit', $page->getTemplate());
		static::assertNotSame([], $this->flash('error'));
	}

	public function testAFormWithErrorsKeepsTheValuesOfTheUser(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->store(['playlist_id' => '1', 'playlist_name' => '', 'screen_width' => '800', 'screen_height' => 'abc']));

		$fields = implode('', array_column($page->getTemplateData()['form_element'], 'HTML_ELEMENT'));
		static::assertMatchesRegularExpression('/name="screen_width"[^>]*value="800"/', $fields);
		// the height is no number: the default stands
		static::assertMatchesRegularExpression('/name="screen_height"[^>]*value="1080"/', $fields);
		static::assertSame('Annas Lobby', $this->databaseValue('SELECT playlist_name FROM playlists WHERE playlist_id = 1'));
	}

	public function testAFormWithErrorsOfAnExistingPlaylistIsShownAgain(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->store(['playlist_id' => '1']));

		static::assertSame('playlists/edit', $page->getTemplate());
		static::assertCount(1, $this->flash('error'));
		static::assertSame('Annas Lobby', $this->databaseValue('SELECT playlist_name FROM playlists WHERE playlist_id = 1'));
	}

	public function testValuesOfTheWrongTypeAreShownAsErrors(): void
	{
		$this->actingAs(2);

		$page = $this->page($this->store(['playlist_mode' => 'master', 'playlist_name' => ['x']]));

		static::assertSame('playlists/edit', $page->getTemplate());
		static::assertCount(1, $this->flash('error'));
	}
}
