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

use App\Framework\Config\Config;
use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Media\DataUrlDecoder;
use App\Framework\Media\MediaUrlSigner;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Playlists\Controller\ShowTemplateComposer;
use App\Modules\Playlists\Helper\Templates\Orchestrator;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Helper\Composer\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Flash\Messages;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The composer of a template item /playlist-item/composer: open it, load and save its content and the rendered image.
 * The images go into a temporary directory. Item 5 of the playlist 1 of anna is the template 7.
 */
#[Group('acl')]
class ShowTemplateComposerTest extends PlaylistsControllerTestCase
{
	private const string PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private MediaWorkspace $workspace;
	private ShowTemplateComposer $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace  = new MediaWorkspace();
		foreach (['originals', 'thumbs'] as $sub)
			mkdir($this->workspace->dir . '/var/storage/playlists/items/' . $sub, 0775, true);
		$this->controller = new ShowTemplateComposer(
			new Orchestrator(
				$this->container->get(ItemsService::class),
				new ExportImage($this->container->get(DataUrlDecoder::class), $this->workspace->handlerFactory()),
				$this->container->get(TemplatePreparer::class),
				$this->container->get(Config::class),
				$this->container->get(MediaUrlSigner::class)
			),
			$this->container->get(JsonResponseHandler::class),
			$this->container->get(Messages::class)
		);
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
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
	 * @param array<string,mixed> $body
	 */
	private function save(array $body): ResponseInterface
	{
		return $this->controller->save($this->request('PATCH', body: $body), $this->newResponse());
	}

	public function testTheComposerOpensWithTheTitleOfTheItem(): void
	{
		$this->actingAs(2);

		$page = $this->controller->show($this->request('GET'), $this->newResponse(), ['item_id' => '5']);

		static::assertInstanceOf(PageResponse::class, $page);
		static::assertSame('templates/canvas', $page->getTemplate());
		static::assertSame('Template Editor: Annas Lobby / Vorlage', $page->getTemplateData()['LANG_PAGE_HEADER']);
		static::assertSame(['ITEM_ID' => 5, 'PLAYLIST_ID' => 1], $page->getTemplateData()['is_playlist_item']);
	}

	public function testWithoutAnItemOrWithoutRightsTheComposerRedirectsToTheList(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->controller->show($this->request('GET'), $this->newResponse(), []), 'Item ID not valid.');
	}

	public function testTheComposerOfAForeignOrUnknownItemRedirectsToTheList(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->controller->show($this->request('GET'), $this->newResponse(), ['item_id' => '7']), 'No rights');
	}

	public function testTheContentIsLoadedWithTheUrlsOfItsImages(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement('UPDATE playlists_items SET content_data = ? WHERE item_id = 5', ['{"objects":[{"type":"image","fileName":"abc123.jpg"},{"type":"rect"}]}']);

		$response = $this->controller->load($this->request('GET'), $this->newResponse(), ['item_id' => '5']);

		$this->assertSuccess($response);
		$content = json_decode($this->json($response)['content'], true);
		static::assertStringContainsString('abc123.jpg', $content['objects'][0]['src']);
		static::assertArrayNotHasKey('src', $content['objects'][1]);
	}

	public function testAnItemWithoutContentLoadsAnEmptyContent(): void
	{
		$this->actingAs(2);

		static::assertSame('', $this->json($this->controller->load($this->request('GET'), $this->newResponse(), ['item_id' => '5']))['content']);
	}

	public function testTheContentOfAForeignItemIsNotLoaded(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->load($this->request('GET'), $this->newResponse(), ['item_id' => '7']), 'No rights');
		$this->assertError($this->controller->load($this->request('GET'), $this->newResponse(), []), 'No rights');
	}

	public function testTheContentAndTheImageAreSaved(): void
	{
		$this->actingAs(2);
		$content = '{"objects":[{"type":"image","fileName":"abc123.jpg","src":"http://evil.example/x.jpg"}]}';

		$response = $this->save(['item_id' => '5', 'content' => $content, 'image' => self::PNG]);

		$this->assertSuccess($response);
		$row = $this->connection->fetchAssociative('SELECT content_data, mimetype FROM playlists_items WHERE item_id = 5');
		static::assertIsArray($row);
		static::assertSame('image/png', $row['mimetype']);
		// the sources of the images are built on loading, they come never from the client
		static::assertSame(['objects' => [['type' => 'image', 'fileName' => 'abc123.jpg']]], json_decode($row['content_data'], true));
		static::assertSame(['5.png'], array_map(static fn(mixed $path): string => basename((string) $path), (array) glob($this->workspace->dir . '/var/storage/playlists/items/originals/*')));
	}

	public function testSaveNeedsAnItemAndRights(): void
	{
		$this->actingAs(2);
		$body = ['item_id' => '5', 'content' => '{"objects":[]}', 'image' => self::PNG];

		$this->assertError($this->save(['item_id' => '0'] + $body), 'No item ID');
		$this->assertError($this->save(['item_id' => '7'] + $body), 'No rights');
		static::assertSame('image/png', $this->databaseValue('SELECT mimetype FROM playlists_items WHERE item_id = 5'));
		static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 7'));
	}

	/**
	 * The composer of the items offers jpg, png, webp and bmp: the thumbnail keeps the format (the preview finds it, see ItemThumbnailResolverTest).
	 */
	public function testTheThumbnailOfAnItemSavedAsPngKeepsTheFormat(): void
	{
		$this->actingAs(2);

		$this->assertSuccess($this->save(['item_id' => '5', 'content' => '{"objects":[]}', 'image' => self::PNG]));

		$thumbnails = array_map(static fn(mixed $path): string => basename((string) $path), (array) glob($this->workspace->dir . '/var/storage/playlists/items/thumbs/*'));
		static::assertSame(['5.png'], $thumbnails);
	}

	public function testAFileWhichIsNoImageIsNotSaved(): void
	{
		$this->actingAs(2);

		$this->assertError($this->save(['item_id' => '5', 'content' => '{"objects":[]}', 'image' => 'data:image/png;base64,' . base64_encode('this is not an image')]), 'Save failed');

		static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 5'));
		static::assertSame([], (array) glob($this->workspace->dir . '/var/storage/playlists/items/originals/*'));
	}

	public function testAContentWhichIsNoDesignLoadsAsEmpty(): void
	{
		$this->actingAs(2);

		foreach (['not json', '{"foo":1}', '{"objects":"x"}'] as $content)
		{
			$this->connection->executeStatement('UPDATE playlists_items SET content_data = ? WHERE item_id = 5', [$content]);

			static::assertSame('', $this->json($this->controller->load($this->request('GET'), $this->newResponse(), ['item_id' => '5']))['content'], $content);
		}
	}

	public function testAContentWithoutObjectsOrWithoutAnImageIsNotSaved(): void
	{
		$this->actingAs(2);

		$this->assertError($this->save(['item_id' => '5', 'content' => '{"foo":1}', 'image' => self::PNG]), 'Save failed');
		$this->assertError($this->save(['item_id' => '5', 'content' => 'no json', 'image' => self::PNG]), 'Save failed');
		static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 5'));
	}

	public function testAnImageWhichIsNoDataUrlIsAnError(): void
	{
		$this->actingAs(2);

		$this->assertError($this->save(['item_id' => '5', 'content' => '{"objects":[]}', 'image' => 'no image']), 'Save failed');
		$this->assertError($this->save(['item_id' => '5', 'content' => '{"objects":[]}', 'image' => 'data:image/png;base64,']), 'Save failed');
		$this->assertError($this->save(['item_id' => '5', 'content' => '{"objects":[]}', 'image' => 'data:image/png;base64,%%%']), 'Save failed');
		$this->assertError($this->save(['item_id' => '5', 'content' => '{"objects":[]}', 'image' => ['x']]), 'Save failed');
		$this->assertError($this->save(['item_id' => '5', 'content' => ['x'], 'image' => self::PNG]), 'Save failed');
		static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 5'));
	}

	/**
	 * Only the items of the type template have a composer, the other ones have a media of the mediapool or a playlist.
	 */
	public function testTheComposerChangesOnlyTemplateItems(): void
	{
		$this->actingAs(2);

		foreach (['1' => 'image/jpeg', '3' => '', '4' => 'application/pdf'] as $itemId => $mimetype)
		{
			$this->assertError($this->save(['item_id' => (string) $itemId, 'content' => '{"objects":[]}', 'image' => self::PNG]), 'Item is not a template.');

			static::assertSame($mimetype, $this->databaseValue('SELECT mimetype FROM playlists_items WHERE item_id = ?', $itemId));
			static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = ?', $itemId));
		}
		static::assertSame([], (array) glob($this->workspace->dir . '/var/storage/playlists/items/*/*'));
	}

	public function testTheComposerOpensAndLoadsOnlyTemplateItems(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE playlists_items SET content_data = '{\"objects\":[]}' WHERE item_id = 1");

		$this->assertRedirect($this->controller->show($this->request('GET'), $this->newResponse(), ['item_id' => '1']), 'Item is not a template.');
		$this->assertError($this->controller->load($this->request('GET'), $this->newResponse(), ['item_id' => '1']), 'Item is not a template.');
		$this->assertError($this->controller->load($this->request('GET'), $this->newResponse(), ['item_id' => '3']), 'Item is not a template.');
	}
}
