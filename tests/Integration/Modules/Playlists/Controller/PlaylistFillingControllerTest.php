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

namespace Tests\Integration\Modules\Playlists\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Llm\LlmClientFactory;
use App\Modules\Playlists\Controller\PlaylistFillingController;
use App\Shared\Llm\LlmTask;
use App\Shared\Llm\UserLlmService;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The suggestion of media for a playlist on a theme /async/playlists/ai/suggest with the AI of the user for texts,
 * which is a fake endpoint here. The media are in the public root (1): bild.jpg (Strand), bild2.jpg (Wald),
 * film.mp4 (Meer, 40 seconds) and doc.pdf (Meer). Playlist 3 belongs to anna, 2 to bernd.
 */
#[Group('acl')]
class PlaylistFillingControllerTest extends PlaylistsControllerTestCase
{
	private ?MockHandler $endpoint = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection->executeStatement("UPDATE mediapool_files SET tags = ',Strand,' WHERE filename = 'bild.jpg'");
		$this->connection->executeStatement("UPDATE mediapool_files SET tags = ',Wald,' WHERE filename = 'bild2.jpg'");
		$this->connection->executeStatement("UPDATE mediapool_files SET tags = ',Meer,', media_description = 'Wellen' WHERE filename IN ('film.mp4', 'doc.pdf')");
	}

	/**
	 * @param array<string,mixed> $body
	 * @param list<Response> $answers
	 */
	private function suggest(array $body, array $answers = []): ResponseInterface
	{
		$this->endpoint = new MockHandler($answers);
		$container = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)])));

		return $container->get(PlaylistFillingController::class)->suggest($this->request('POST', '/async/playlists/ai/suggest', $body), $this->newResponse());
	}

	private static function answer(string $content): Response
	{
		return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
			'id' => 'chatcmpl-1', 'object' => 'chat.completion', 'created' => 1, 'model' => 'm',
			'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']]
		]));
	}

	private function withLlm(int $UID): void
	{
		$this->connection->executeStatement("INSERT INTO company_llm_tasks (company_id, task, mode) VALUES (1, ?, 'user_own')", [LlmTask::TEXT]);
		$service = $this->container->get(UserLlmService::class);
		$service->saveConnection($UID, 0, ['name' => 'Ollama', 'api_type' => 'openai', 'base_url' => 'http://localhost:11434/v1', 'api_key' => '']);
		$service->saveTasks($UID, [LlmTask::TEXT => ['connection_id' => $service->findConnections($UID)[0]['connection_id'], 'model' => 'qwen3']]);
	}

	public function testTheAiFindsTheMediaByItsTermsAndChoosesThem(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->suggest(['playlist_id' => 3, 'theme' => 'Urlaub am Meer', 'minutes' => 2], [
			self::answer('<think>beach</think>{"terms": ["Strand", "Meer", "strand", "x"]}'),
			self::answer('{"items": [2, 1, 99, 2, "1"]}')
		]);

		$this->assertSuccess($response);
		$items = $this->json($response)['items'];
		static::assertSame(['film.mp4', 'bild.jpg'], array_column($items, 'filename'));
		static::assertSame([40, 15], array_column($items, 'seconds'));
		static::assertSame(['media-id-0000003', 'media-id-0000001'], array_column($items, 'media_id'));
		static::assertStringContainsString('sig=', $items[0]['thumbnail']);

		// the choice: the theme, the seconds and only the pictures and videos which were found
		$body = json_decode((string) $this->endpoint?->getLastRequest()?->getBody(), true);
		static::assertIsArray($body);
		static::assertStringContainsString('120 seconds', $body['messages'][0]['content']);
		$text = $body['messages'][1]['content'];
		static::assertStringStartsWith("Theme: Urlaub am Meer\nMedia: ", $text);
		$media = json_decode(substr($text, (int) strpos($text, '[')), true);
		static::assertIsArray($media);
		static::assertSame([
			['number' => 1, 'name' => 'bild.jpg', 'tags' => ['Strand'], 'description' => '', 'kind' => 'image', 'seconds' => 15],
			['number' => 2, 'name' => 'film.mp4', 'tags' => ['Meer'], 'description' => 'Wellen', 'kind' => 'video', 'seconds' => 40]
		], $media);
	}

	public function testWithoutMatchingMediaTheAiIsNotAskedToChoose(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->suggest(['playlist_id' => 3, 'theme' => 'Weltraum'], [self::answer('{"terms": ["Rakete", "Mond"]}')]);

		$this->assertError($response, $this->text('ai_nothing_found'));
		static::assertSame(0, $this->endpoint?->count());
	}

	public function testAChoiceOfNothingIsNothingFound(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->suggest(['playlist_id' => 3, 'theme' => 'Meer'], [self::answer('{"terms": ["Meer"]}'), self::answer('{"items": []}')]);

		$this->assertError($response, $this->text('ai_nothing_found'));
	}

	public function testWithoutAnLlmNothingIsSent(): void
	{
		$this->actingAs(2);

		$this->assertError($this->suggest(['playlist_id' => 3, 'theme' => 'Meer']), $this->text('llm_not_configured', 'main'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testAPlaylistOfAnotherUserGetsNoSuggestion(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->expectException(AccessDeniedException::class);
		$this->suggest(['playlist_id' => 2, 'theme' => 'Meer']);
	}

	public function testATooLongThemeIsNotSent(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->suggest(['playlist_id' => 3, 'theme' => str_repeat('a', 301)]), $this->text('llm_input_too_long', 'main'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testAThemeIsNeeded(): void
	{
		$this->actingAs(2);

		$this->assertError($this->suggest(['playlist_id' => 3, 'theme' => '  ']), $this->text('ai_theme_missing'));
		$this->assertError($this->suggest(['theme' => 'Meer']), $this->text('ai_theme_missing'));
	}
}
