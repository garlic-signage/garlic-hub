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

namespace Tests\Blackbox\Modules\Player;

use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * What a media player does: it asks /smil-index and sends its identity in the User-Agent. There is no
 * login, the player is known by its UUID. Users: admin (1), anna (2), bernd (3).
 */
#[Group('blackbox')]
class PlayerIndexTest extends BlackboxTestCase
{
	private const string UUID_A = '3f0cd56c-d511-486a-a8e1-9d2cefd78b3f';
	private const string UUID_B = '7a1d0e2b-5c4f-4b8a-9e63-1f2a3b4c5d6e';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	private function agent(string $uuid, string $name = 'lobby-player'): string
	{
		return 'GAPI/1.0 (UUID:' . $uuid . '; NAME:' . $name . ') garlic-macOS/v0.6.0.679 (MODEL:Garlic)';
	}

	/**
	 * @param array<string,string> $headers
	 */
	private function ask(string $uuid, int $ownerId = 2, string $method = 'GET', array $headers = [], string $name = 'lobby-player'): ResponseInterface
	{
		return $this->browser()->request($method, '/smil-index?owner_id=' . $ownerId, ['headers' => ['User-Agent' => $this->agent($uuid, $name)] + $headers]);
	}

	public function testAnUnknownKindOfClientGetsTheForbiddenIndexAndIsNotRegistered(): void
	{
		$response = $this->browser()->request('GET', '/smil-index?owner_id=2', ['headers' => ['User-Agent' => 'curl/8.5']]);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('application/smil+xml', $response->getHeaderLine('Content-Type'));
		static::assertStringContainsString('not supported media player', (string) $response->getBody());
		static::assertStringContainsString('forbidden.smil', $response->getHeaderLine('Content-Disposition'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player'));
	}

	public function testANewPlayerIsRegisteredForTheOwnerAndGetsTheStartIndex(): void
	{
		$response = $this->ask(self::UUID_A);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('application/smil+xml', $response->getHeaderLine('Content-Type'));
		static::assertStringContainsString('unreleased.smil', $response->getHeaderLine('Content-Disposition'));
		static::assertStringStartsWith('<?xml', (string) $response->getBody());

		$player = $this->connection->fetchAssociative('SELECT UID, player_name, uuid FROM player');
		static::assertIsArray($player);
		static::assertSame(2, (int) $player['UID']);
		static::assertSame('lobby-player', $player['player_name']);
		static::assertSame(self::UUID_A, $player['uuid']);
	}

	public function testThePlayerIsRegisteredOnlyOnceAndGetsTheIndexOfAReleasedPlayerAfterwards(): void
	{
		$this->ask(self::UUID_A);
		$second = $this->ask(self::UUID_A);

		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player'));
		static::assertSame(200, $second->getStatusCode());
		// in the edge edition the new player is released at once, without playlist it gets the empty index
		static::assertStringContainsString('released.smil', $second->getHeaderLine('Content-Disposition'));
		static::assertStringNotContainsString('unreleased', $second->getHeaderLine('Content-Disposition'));
	}

	public function testTwoPlayersAreTwoPlayersWithTheirOwners(): void
	{
		$this->ask(self::UUID_A, 2, name: 'lobby');
		$this->ask(self::UUID_B, 3, name: 'canteen');

		$rows = $this->connection->fetchAllAssociative('SELECT uuid, UID, player_name FROM player ORDER BY player_id');
		static::assertSame([[self::UUID_A, 2, 'lobby'], [self::UUID_B, 3, 'canteen']], array_map(static fn(array $r): array => [$r['uuid'], (int) $r['UID'], $r['player_name']], $rows));
	}

	public function testAnswersForPlayersAreOpenForJavascriptPlayersAndCachedByTheBrowser(): void
	{
		$response = $this->ask(self::UUID_A);

		static::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
		static::assertStringContainsString('X-Signage-Agent', $response->getHeaderLine('Access-Control-Allow-Headers'));
		static::assertNotSame('', $response->getHeaderLine('etag'));
		static::assertNotSame('', $response->getHeaderLine('Last-Modified'));
	}

	public function testTheHeadRequestOfThePlayerGivesTheHeadersWithoutTheBody(): void
	{
		$this->ask(self::UUID_A);

		$head = $this->ask(self::UUID_A, method: 'HEAD');

		static::assertSame(200, $head->getStatusCode());
		static::assertSame('', (string) $head->getBody());
		static::assertNotSame('', $head->getHeaderLine('etag'));
		static::assertSame('application/smil+xml', $head->getHeaderLine('Content-Type'));
	}

	/**
	 * A player sends both headers of its last answer. The server then answers with 304 and no body.
	 */
	public function testAPlayerWhichHasTheCurrentIndexGetsNotModified(): void
	{
		$this->ask(self::UUID_A); // the registration
		$first = $this->ask(self::UUID_A);

		$again = $this->ask(self::UUID_A, headers: [
			'If-None-Match'     => $first->getHeaderLine('etag'),
			'If-Modified-Since' => $first->getHeaderLine('Last-Modified'),
		]);

		static::assertSame(304, $again->getStatusCode());
		static::assertSame('', (string) $again->getBody());
	}

	public function testAPlayerWithAnOldEtagGetsTheIndexAgain(): void
	{
		$this->ask(self::UUID_A); // the registration
		$first = $this->ask(self::UUID_A);

		$again = $this->ask(self::UUID_A, headers: [
			'If-None-Match'     => 'not-the-current-etag',
			'If-Modified-Since' => $first->getHeaderLine('Last-Modified'),
		]);

		static::assertSame(200, $again->getStatusCode());
		static::assertSame((string) $first->getBody(), (string) $again->getBody());
	}

	public function testAJavascriptPlayerIdentifiesItselfWithAnExtraHeader(): void
	{
		$response = $this->browser()->request('GET', '/smil-index?owner_id=3', ['headers' => [
			'User-Agent'      => 'Mozilla/5.0 (X11; Linux x86_64)',
			'X-Signage-Agent' => $this->agent(self::UUID_B, 'browser-player'),
		]]);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('browser-player', $this->connection->fetchOne('SELECT player_name FROM player'));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT UID FROM player'));
	}

	public function testAnOwnerIdWhichIsNoNumberBecomesNoOwner(): void
	{
		$response = $this->browser()->request('GET', '/smil-index?owner_id=abc', ['headers' => ['User-Agent' => $this->agent(self::UUID_A)]]);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT UID FROM player'));
	}

	public function testSqlInTheOwnerIdAndTheAgentDoesNothing(): void
	{
		$this->browser()->request('GET', "/smil-index?owner_id=2'%20OR%20'1'%3D'1", ['headers' => ['User-Agent' => $this->agent(self::UUID_A, "x'); DROP TABLE player;--")]]);

		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player'));
		static::assertSame("x'); DROP TABLE player;--", $this->connection->fetchOne('SELECT player_name FROM player'));
	}

	/**
	 * A playlist with one image of the media pool, for the player of the given owner.
	 */
	private function playlistWithImage(int $playlistId, string $name, string $checksum, int $UID): void
	{
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES ($playlistId, $UID, 'master', '$name')");
		$this->connection->executeStatement(
			"INSERT INTO mediapool_files (media_id, node_id, UID, checksum, mimetype, filename, extension, thumb_extension, metadata) VALUES ('media-$playlistId', 2, $UID, '$checksum', 'image/jpeg', '$name.jpg', 'jpg', 'jpg', '{}')"
		);
		$this->connection->executeStatement(
			"INSERT INTO playlists_items (playlist_id, UID, item_order, item_duration, item_type, file_resource, mimetype, item_name) VALUES ($playlistId, $UID, 1, 10, 'mediapool', '$checksum', 'image/jpeg', '$name.jpg')"
		);
	}

	/**
	 * The user publishes the playlist in the browser: the index of the players is built from this export.
	 */
	private function exportPlaylist(string $username, int $playlistId): void
	{
		$browser = $this->browser();
		$browser->login($username, 'Test1234!');
		$response = $browser->request('PUT', '/async/playlists', ['form_params' => ['playlist_id' => $playlistId, 'csrf_token' => $browser->csrfToken('/')]]);

		static::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
	}

	public function testAPlayerWithAPlaylistGetsTheContentOfItsOwnPlaylistOnly(): void
	{
		$checksumA = str_repeat('a', 64);
		$checksumB = str_repeat('b', 64);
		$this->playlistWithImage(5, 'lobby', $checksumA, 2);
		$this->playlistWithImage(6, 'canteen', $checksumB, 3);
		$this->exportPlaylist('anna', 5);
		$this->exportPlaylist('bernd', 6);
		$this->ask(self::UUID_A, 2, name: 'lobby');
		$this->ask(self::UUID_B, 3, name: 'canteen');
		$this->connection->executeStatement("UPDATE player SET playlist_id = 5 WHERE uuid = '" . self::UUID_A . "'");
		$this->connection->executeStatement("UPDATE player SET playlist_id = 6 WHERE uuid = '" . self::UUID_B . "'");

		$a = $this->ask(self::UUID_A, 2, name: 'lobby');
		$b = $this->ask(self::UUID_B, 3, name: 'canteen');

		static::assertSame(200, $a->getStatusCode());
		static::assertStringContainsString($checksumA, (string) $a->getBody());
		static::assertStringNotContainsString($checksumB, (string) $a->getBody());
		static::assertStringContainsString($checksumB, (string) $b->getBody());
		static::assertStringNotContainsString($checksumA, (string) $b->getBody());
	}

	/**
	 * A player never sends a cookie back. If every answer started a session, the server would
	 * collect a session file per request, and the cookie would make the cacheable answer uncacheable
	 * for proxies.
	 */
	public function testThePlayerGetsNoSessionAndNoCookie(): void
	{
		for ($i = 0; $i < 5; $i++)
		{
			$response = $this->ask(self::UUID_A);
			static::assertSame([], $response->getHeader('Set-Cookie'), 'Request ' . $i);
		}

		static::assertSame([], glob($this->deployment()->directory() . '/var/sessions/sess_*') ?: []);
		static::assertSame([], glob($this->deployment()->directory() . '/tmp/sess_*') ?: []);
	}

	public function testThePlayerPageIsNotForPlayers(): void
	{
		$response = $this->browser()->request('GET', '/player', ['headers' => ['User-Agent' => $this->agent(self::UUID_A)]]);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
	}
}
