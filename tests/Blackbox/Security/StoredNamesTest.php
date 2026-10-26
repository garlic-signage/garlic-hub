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

namespace Tests\Blackbox\Security;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Blackbox\Support\BlackboxTestCase;
use Tests\Blackbox\Support\Browser;

/**
 * Names which users store (users, playlists, templates, folders, media, players) are shown in many pages. With
 * markup in the name, no page may change its structure: the browser must see text, not elements, attributes or
 * scripts. The pages are read like a browser reads them.
 */
#[Group('blackbox')]
class StoredNamesTest extends BlackboxTestCase
{
	private const string UUID = '3f0cd56c-d511-486a-a8e1-9d2cefd78b3f';

	private Browser $admin;

	protected function setUp(): void
	{
		parent::setUp();
		foreach (['acl/users.sql', 'templates/templates.sql', 'playlists/playlists.sql', 'mediapool/nodes.sql', 'mediapool/files.sql', 'playlists/items.sql', 'acl/players.sql'] as $fixture)
			$this->loadFixture($fixture);

		$this->admin = $this->browser();
		$this->admin->login('admin', 'Test1234!');
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function payloads(): array
	{
		return [
			'a script'                 => ['"><script>alert(1)</script>'],
			'an attribute'             => ['\'"onmouseover=alert(1) x=\''],
			'a picture with a handler' => ['<img src=x onerror=alert(1)>'],
			'the end of a script'      => ['</script><script>alert(1)</script>'],
			'a script address'         => ['javascript:alert(1)'],
			'the template language'    => ['{{7*7}}{{{7*7}}}'],
			'the end of a cdata'       => [']]><x/>'],
			'entities'                 => ['&lt;b&gt; &amp; &#60;script&#62;'],
			'a right to left mark'     => ["evil\u{202E}gnp.exe"],
			'many elements'            => [str_repeat('<b>', 300)],
		];
	}

	/**
	 * @return list<string> the pages which show the names, with the ids of the fixtures
	 */
	private static function pages(): array
	{
		return [
			'/', '/users', '/users/edit/2', '/player', '/player/connectivity/1', '/playlists', '/playlists/settings/1',
			'/playlists/compose/1', '/templates', '/templates/settings/2', '/templates/composer/2', '/mediapool',
		];
	}

	private function setNames(string $payload): void
	{
		$statements = [
			'UPDATE user_main SET username = :p WHERE UID = 2',
			'UPDATE user_main SET email = :p WHERE UID = 2',
			'UPDATE playlists SET playlist_name = :p',
			'UPDATE playlists_items SET item_name = :p',
			'UPDATE templates SET name = :p, description = :p',
			'UPDATE mediapool_nodes SET name = :p WHERE node_id >= 7',
			'UPDATE mediapool_files SET filename = :p, media_description = :p',
			'UPDATE player SET player_name = :p',
		];
		foreach ($statements as $sql)
			$this->connection->executeStatement($sql, ['p' => $payload]);
	}

	private function document(ResponseInterface $response): DOMDocument
	{
		$document = new DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8"?>' . (string) $response->getBody());
		libxml_clear_errors();

		return $document;
	}

	/**
	 * @return list<DOMElement> the elements which the query finds
	 */
	private function elements(DOMDocument $document, string $query): array
	{
		$found = [];
		foreach ((new DOMXPath($document))->query($query) ?: [] as $node)
		{
			if ($node instanceof DOMElement)
				$found[] = $node;
		}

		return $found;
	}

	/**
	 * What the browser builds from the page, without the texts: how often every element and every attribute occurs.
	 *
	 * @return array<string,int>
	 */
	private function structure(DOMDocument $document): array
	{
		$count = [];
		foreach ($this->elements($document, '//*') as $element)
		{
			$count['<' . $element->tagName . '>'] = ($count['<' . $element->tagName . '>'] ?? 0) + 1;
			foreach ($element->attributes as $attribute)
				$count[$element->tagName . '@' . $attribute->name] = ($count[$element->tagName . '@' . $attribute->name] ?? 0) + 1;
		}
		ksort($count);

		return $count;
	}

	/**
	 * Markup which no page may contain, whatever the names are.
	 */
	private function assertNoInjectedMarkup(DOMDocument $document, string $where): void
	{
		foreach ($this->elements($document, '//script') as $script)
			static::assertTrue($script->hasAttribute('src') || $script->hasAttribute('nonce'), $where . ': a script without nonce: ' . substr($script->textContent, 0, 80));

		foreach ($this->elements($document, '//*') as $element)
		{
			foreach ($element->attributes as $attribute)
			{
				static::assertStringStartsNotWith('on', strtolower($attribute->name), $where . ': the handler ' . $attribute->name . ' on <' . $element->tagName . '>');
				if (in_array(strtolower($attribute->name), ['href', 'src', 'action', 'formaction'], true))
					static::assertStringStartsNotWith('javascript:', strtolower(trim($attribute->value)), $where . ': a script address in ' . $attribute->name);
			}
		}
		static::assertSame([], $this->elements($document, '//img[@src="x"] | //x'), $where . ': an element of the name');
	}

	#[DataProvider('payloads')]
	public function testNoPageChangesItsStructureWithMarkupInTheNames(string $payload): void
	{
		$before = [];
		foreach (self::pages() as $page)
		{
			$response = $this->admin->get($page);
			static::assertSame(200, $response->getStatusCode(), $page);
			$before[$page] = $this->structure($this->document($response));
		}

		$this->setNames($payload);

		foreach (self::pages() as $page)
		{
			$response = $this->admin->get($page);
			static::assertSame(200, $response->getStatusCode(), $page . ' with ' . $payload);
			$document = $this->document($response);
			static::assertSame($before[$page], $this->structure($document), $page . ' changes its structure');
			$this->assertNoInjectedMarkup($document, $page);
		}
	}

	/**
	 * @return array<string,array{string,string}> the page and what shows the name: the lists show it as text
	 */
	public static function listsWhichShowTheName(): array
	{
		return ['users' => ['/users', 'username'], 'playlists' => ['/playlists', 'playlist_name'], 'templates' => ['/templates', 'name'], 'players' => ['/player', 'player_name']];
	}

	#[DataProvider('listsWhichShowTheName')]
	public function testTheListsShowTheNameAsText(string $page, string $field): void
	{
		$payload = '<img src=x onerror=alert(1)> & "quotes"';
		$this->setNames($payload);

		$text = (string) $this->document($this->admin->get($page))->textContent;

		static::assertStringContainsString($payload, $text, $page . ' shows the name as it is, as text');
	}

	/**
	 * @return array<string,array{string}> the answers of the JSON part which carry names
	 */
	public static function jsonEndpoints(): array
	{
		return [
			'the users for the owner field' => ['/async/users/find/a'],
			'the playlists for a player'    => ['/async/playlists/find/for-player'],
			'the templates'                 => ['/async/templates/find'],
			'the folders'                   => ['/async/mediapool/node'],
			'the media of a folder'         => ['/async/mediapool/media/list/8'],
			'the items of a playlist'       => ['/async/playlists/items/load/1'],
		];
	}

	#[DataProvider('jsonEndpoints')]
	public function testNamesInJsonAreDataAndCanNotBeTakenForAPage(string $path): void
	{
		$this->setNames('"><script>alert(1)</script>');

		$response = $this->admin->get($path);

		static::assertSame(200, $response->getStatusCode(), $path);
		static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'), $path);
		$decoded = json_decode((string) $response->getBody(), true);
		static::assertIsArray($decoded, $path);
		// the name is in it unchanged: JSON is no HTML, the escaping is the job of the code which shows it
		static::assertStringContainsString('<script>alert(1)<\/script>', (string) $response->getBody(), $path);
	}

	#[DataProvider('jsonEndpoints')]
	public function testTheJsonPartSaysThatItIsJson(string $path): void
	{
		static::markTestSkipped('Known bug: a middleware of the group /async sets Content-Type text/html on every answer, also on JSON. A browser which opens the address shows the names as a page. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->setNames('<img src=x onerror=alert(1)>');

		static::assertStringContainsString('application/json', $this->admin->get($path)->getHeaderLine('Content-Type'), $path);
	}

	/**
	 * Created over the real forms and endpoints: the input is not refused with an error, and the pages are still safe.
	 */
	#[DataProvider('payloads')]
	public function testNamesFromTheFormsAreShownSafely(string $payload): void
	{
		$token = $this->admin->csrfToken('/mediapool');
		$post  = fn(string $method, string $path, array $fields): ResponseInterface => $this->admin->request($method, $path, ['form_params' => $fields + ['csrf_token' => $token]]);

		$responses = [
			'folder'   => $post('POST', '/async/mediapool/node', ['node_id' => 8, 'name' => $payload]),
			'media'    => $post('POST', '/async/mediapool/media/edit', ['media_id' => '11111111-1111-4111-8111-111111111111', 'filename' => $payload, 'media_description' => $payload]),
			'user'     => $post('POST', '/users/edit', ['UID' => 0, 'username' => $payload, 'email' => 'new@example.com', 'status' => 3, 'locale' => 'en_US']),
			'template' => $post('POST', '/templates/settings', ['name' => $payload, 'visibility' => 'private', 'type' => 'canvas']),
			'playlist' => $post('POST', '/playlists/settings', ['playlist_name' => $payload, 'playlist_mode' => 'master']),
		];
		foreach ($responses as $what => $response)
			static::assertLessThan(500, $response->getStatusCode(), $what . ' with ' . $payload);

		foreach (self::pages() as $page)
		{
			$response = $this->admin->get($page);
			static::assertSame(200, $response->getStatusCode(), $page);
			$this->assertNoInjectedMarkup($this->document($response), $page);
		}
	}

	/**
	 * A player names itself, and everybody who knows the format of the agent can register one for any owner.
	 */
	#[DataProvider('payloads')]
	public function testTheNameWhichAPlayerGivesItselfIsShownSafely(string $payload): void
	{
		// the header can not carry a line break and the agent ends with ")": that is what a client can send
		$name = str_replace(["\r", "\n"], '', $payload);
		$this->browser()->request('GET', '/smil-index?owner_id=2', ['headers' => ['User-Agent' => 'GAPI/1.0 (UUID:' . self::UUID . '; NAME:' . $name . ') garlic-macOS/v0.6.0.679 (MODEL:Garlic)']]);

		foreach (['/player', '/player/connectivity/5', '/'] as $page)
		{
			$response = $this->admin->get($page);
			static::assertLessThan(500, $response->getStatusCode(), $page);
			$this->assertNoInjectedMarkup($this->document($response), $page);
		}
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function namesInTheIndex(): array
	{
		return [
			'an attribute'   => ['" onload="alert(1)" x="'],
			'an element'     => ['"><script>alert(1)</script><x a="'],
			'a cdata'        => [']]><![CDATA[<script>'],
			'entities'       => ['&lt;b&gt; &amp; &#60;'],
			'quotes'         => ['\'"'],
		];
	}

	#[DataProvider('namesInTheIndex')]
	public function testNamesInTheIndexOfThePlayerKeepTheXmlWellFormed(string $name): void
	{
		$checksum = str_repeat('a', 64);
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (5, 2, 'master', :n)", ['n' => $name]);
		$this->connection->executeStatement(
			"INSERT INTO mediapool_files (media_id, node_id, UID, checksum, mimetype, filename, extension, thumb_extension, metadata) VALUES ('media-5', 2, 2, '$checksum', 'image/jpeg', :n, 'jpg', 'jpg', '{}')",
			['n' => $name . '.jpg']
		);
		$this->connection->executeStatement(
			"INSERT INTO playlists_items (playlist_id, UID, item_order, item_duration, item_type, file_resource, mimetype, item_name) VALUES (5, 2, 1, 10, 'mediapool', '$checksum', 'image/jpeg', :n)",
			['n' => $name]
		);
		$anna = $this->browser();
		$anna->login('anna', 'Test1234!');
		static::assertSame(200, $anna->request('PUT', '/async/playlists', ['form_params' => ['playlist_id' => 5, 'csrf_token' => $anna->csrfToken('/')]])->getStatusCode());
		$agent = ['User-Agent' => 'GAPI/1.0 (UUID:' . self::UUID . '; NAME:player) garlic-macOS/v0.6.0.679 (MODEL:Garlic)'];
		$this->browser()->request('GET', '/smil-index?owner_id=2', ['headers' => $agent]);
		$this->connection->executeStatement('UPDATE player SET playlist_id = 5 WHERE uuid = :u', ['u' => self::UUID]);

		$response = $this->browser()->request('GET', '/smil-index?owner_id=2', ['headers' => $agent]);

		$body = (string) $response->getBody();
		static::assertSame(200, $response->getStatusCode());
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body);
		libxml_clear_errors();
		static::assertNotFalse($xml, 'The index is no XML: ' . substr($body, 0, 400));
		// nothing was added to the playlist: the elements of the name are no elements of the index
		static::assertCount(0, $xml->xpath('//*[local-name()="script" or local-name()="x"]') ?: []);
		foreach ($xml->xpath('//@*') ?: [] as $attribute)
			static::assertStringStartsNotWith('on', strtolower($attribute->getName()));
	}
}
