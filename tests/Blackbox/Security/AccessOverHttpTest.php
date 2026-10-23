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

namespace Tests\Blackbox\Security;

use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Blackbox\Support\Browser;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * Anna tries over HTTP what she must not do with the data of Bernd and of the admin. The same
 * requests the pages send, with her own session and a valid CSRF token, so only the rights decide.
 * Users: admin (1), anna (2), bernd (3). Playlist 1 belongs to anna with the items 1 to 5, playlist 2
 * to bernd with the items 6 and 7. Mediapool: anna 7 (8 Fotos), bernd 10 (11 Privat, 12 Geteilt).
 */
#[Group('blackbox')]
class AccessOverHttpTest extends BlackboxTestCase
{
	private Browser $anna;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('mediapool/files.sql');
		$this->loadFixture('playlists/items.sql');

		$this->anna = $this->browser();
		$this->anna->login('anna', 'Test1234!');
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	private function send(string $method, string $path, array $fields = []): ResponseInterface
	{
		$fields['csrf_token'] = $this->anna->csrfToken('/');

		return $this->anna->request($method, $path, ['form_params' => $fields]);
	}

	private function assertRefused(ResponseInterface $response, string $what): void
	{
		static::assertGreaterThanOrEqual(400, $response->getStatusCode(), $what . ' must be refused, got ' . $response->getStatusCode() . ': ' . substr((string) $response->getBody(), 0, 200));
		$json = json_decode((string) $response->getBody(), true);
		if (is_array($json))
			static::assertFalse($json['success'] ?? null, $what);
	}

	private function scalar(string $sql): int
	{
		return (int) $this->connection->fetchOne($sql);
	}

	public function testAnnaMayNotDeleteTheItemOfBerndsPlaylist(): void
	{
		$this->assertRefused($this->send('DELETE', '/async/playlists/items', ['playlist_id' => 2, 'item_id' => 6]), 'delete item of a foreign playlist');
		static::assertSame(1, $this->scalar('SELECT COUNT(*) FROM playlists_items WHERE item_id = 6'));
	}

	/**
	 * The client sends its own playlist and the item of someone else.
	 */
	public function testAnnaMayNotDeleteAForeignItemByNamingHerOwnPlaylist(): void
	{
		$this->assertRefused($this->send('DELETE', '/async/playlists/items', ['playlist_id' => 1, 'item_id' => 6]), 'delete a foreign item through the own playlist');
		static::assertSame(1, $this->scalar('SELECT COUNT(*) FROM playlists_items WHERE item_id = 6'));
	}

	public function testAnnaMayNotReorderForeignItemsByNamingHerOwnPlaylist(): void
	{
		$this->assertRefused($this->send('PATCH', '/async/playlists/items', ['playlist_id' => 1, 'items_positions' => ['7', '6']]), 'reorder foreign items');
		static::assertSame(1, $this->scalar('SELECT item_order FROM playlists_items WHERE item_id = 6'));
	}

	public function testAnnaMayDeleteAnItemOfHerOwnPlaylist(): void
	{
		$response = $this->send('DELETE', '/async/playlists/items', ['playlist_id' => 1, 'item_id' => 4]);

		static::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		static::assertSame(0, $this->scalar('SELECT COUNT(*) FROM playlists_items WHERE item_id = 4'));
	}

	public function testAnnaMayNotPublishBerndsPlaylist(): void
	{
		$this->assertRefused($this->send('PUT', '/async/playlists', ['playlist_id' => 2]), 'publish a foreign playlist');
	}

	public function testAnnaMayNotMoveADirectoryOfBernd(): void
	{
		$this->assertRefused($this->send('POST', '/async/mediapool/node/move', ['src_node_id' => 11, 'target_node_id' => 9, 'target_region' => 'appendChild']), 'move a foreign directory');
		static::assertSame(10, $this->scalar('SELECT parent_id FROM mediapool_nodes WHERE node_id = 11'));
	}

	public function testAnnaMayNotMoveHerDirectoryIntoTheTreeOfBernd(): void
	{
		$this->assertRefused($this->send('POST', '/async/mediapool/node/move', ['src_node_id' => 8, 'target_node_id' => 11, 'target_region' => 'appendChild']), 'move into a foreign tree');
		static::assertSame(7, $this->scalar('SELECT parent_id FROM mediapool_nodes WHERE node_id = 8'));
	}

	public function testAnnaMayNotListThePrivateMediaOfBernd(): void
	{
		$response = $this->anna->get('/async/mediapool/media/list/11');

		$this->assertRefused($response, 'list a private directory');
		static::assertStringNotContainsString('geheim.png', (string) $response->getBody());
	}

	public function testAnnaMayListThePublicMediaOfBerndAndHerOwn(): void
	{
		static::assertStringContainsString('teilen.png', (string) $this->anna->get('/async/mediapool/media/list/12')->getBody());
		static::assertStringContainsString('urlaub.jpg', (string) $this->anna->get('/async/mediapool/media/list/8')->getBody());
	}

	public function testAnnaMayNotChangeOrDeleteMediaOfBernd(): void
	{
		$privateMedia = '33333333-3333-4333-8333-333333333333';
		$publicMedia  = '44444444-4444-4444-8444-444444444444';

		$this->assertRefused($this->send('POST', '/async/mediapool/media/edit', ['media_id' => $privateMedia, 'filename' => 'x.png', 'media_description' => 'x']), 'change private media');
		$this->assertRefused($this->send('DELETE', '/async/mediapool/media', ['media_id' => $publicMedia]), 'delete public media of another user');
		$this->assertRefused($this->send('POST', '/async/mediapool/media/move', ['media_id' => $publicMedia, 'node_id' => 9]), 'take public media away');

		static::assertSame('geheim.png', $this->connection->fetchOne("SELECT filename FROM mediapool_files WHERE media_id = '$privateMedia'"));
		static::assertSame(0, $this->scalar("SELECT deleted FROM mediapool_files WHERE media_id = '$publicMedia'"));
		static::assertSame(12, $this->scalar("SELECT node_id FROM mediapool_files WHERE media_id = '$publicMedia'"));
	}

	public function testAnnaMayNotResetThePasswordOrTheStatusOfTheAdmin(): void
	{
		$before = $this->connection->fetchAssociative('SELECT password, status, email FROM user_main WHERE UID = 1');

		$this->send('POST', '/users/edit', ['UID' => 1, 'resetPassword' => 1, 'username' => 'admin', 'email' => 'admin@example.com']);
		$this->send('POST', '/users/edit', ['UID' => 1, 'username' => 'admin', 'email' => 'hacked@example.com', 'status' => 0, 'locale' => 'en_US']);

		static::assertSame($before, $this->connection->fetchAssociative('SELECT password, status, email FROM user_main WHERE UID = 1'));
		static::assertSame(0, $this->scalar('SELECT COUNT(*) FROM user_tokens WHERE UID = 1'));
	}

	public function testAnnaMayNotDeleteAUser(): void
	{
		$this->assertRefused($this->send('DELETE', '/async/users', ['UID' => 3]), 'delete a user');
		static::assertSame('bernd', $this->connection->fetchOne('SELECT username FROM user_main WHERE UID = 3'));
	}

	public function testAnnaMayNotHandleTheTokensOfAnotherUser(): void
	{
		$this->assertRefused($this->send('POST', '/async/users/tokens', ['UID' => 3, 'purpose' => 'password_reset']), 'refresh a token');
		$this->assertRefused($this->send('DELETE', '/async/users/tokens', ['UID' => 3, 'purpose' => 'password_reset']), 'delete a token');
	}

	public function testAnnaMayNotOpenTheTemplateSettingsOfTheAdmin(): void
	{
		$response = $this->anna->get('/templates/settings/1');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/templates', $response->getHeaderLine('Location'));
		static::assertStringNotContainsString('Admin Vorlage', (string) $response->getBody());
	}

	public function testAnnaSeesOnlyHerOwnPlaylistsInTheList(): void
	{
		$html = (string) $this->anna->get('/playlists')->getBody();

		static::assertStringContainsString('Annas Lobby', $html);
		static::assertStringNotContainsString('Bernds Kantine', $html);
	}

	public function testRequestsWithoutAValidCsrfTokenChangeNothing(): void
	{
		$response = $this->anna->request('DELETE', '/async/playlists/items', ['form_params' => ['playlist_id' => 1, 'item_id' => 4, 'csrf_token' => 'forged']]);
		$this->assertRefused($response, 'forged CSRF token');
		$this->assertRefused($this->anna->request('DELETE', '/async/playlists/items', ['form_params' => ['playlist_id' => 1, 'item_id' => 4]]), 'missing CSRF token');

		static::assertSame(1, $this->scalar('SELECT COUNT(*) FROM playlists_items WHERE item_id = 4'));
	}
}
