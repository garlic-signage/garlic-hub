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

namespace Tests\Integration\Modules\Templates\Controller;

use App\Framework\Llm\LlmClientFactory;
use App\Modules\Templates\Controller\ImageSuggestionController;
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
 * The pictures for the texts of a template /async/templates/suggest-images with the AI of the user for texts, which
 * is a fake endpoint here. The media are in the public root (1): bild.jpg (Strand), bild2.jpg (Wald), film.mp4 and
 * doc.pdf (both Meer).
 */
#[Group('acl')]
class ImageSuggestionControllerTest extends TemplatesControllerTestCase
{
	private ?MockHandler $endpoint = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('mediapool/nodes.sql');
		$this->connection->executeStatement("UPDATE mediapool_files SET tags = ',Strand,', media_description = 'Sand und Sonne' WHERE filename = 'bild.jpg'");
		$this->connection->executeStatement("UPDATE mediapool_files SET tags = ',Wald,' WHERE filename = 'bild2.jpg'");
		$this->connection->executeStatement("UPDATE mediapool_files SET tags = ',Meer,' WHERE filename IN ('film.mp4', 'doc.pdf')");
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

		return $container->get(ImageSuggestionController::class)->suggest($this->request('POST', '/async/templates/suggest-images', $body), $this->newResponse());
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

	/**
	 * @return array<mixed>
	 */
	private function lastRequestBody(): array
	{
		$body = json_decode((string) $this->endpoint?->getLastRequest()?->getBody(), true);
		static::assertIsArray($body);

		return $body;
	}

	public function testTheAiFindsPicturesByTheTextsAndChoosesThem(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->suggest(['texts' => "Sommerfest\nam Strand"], [
			self::answer('<think>beach</think>{"terms": ["Strand", "Meer", "Wald", "strand", "x"]}'),
			self::answer('{"pictures": [2, 1, 99, 2, "1"]}')
		]);

		$this->assertSuccess($response);
		$images = $this->json($response)['images'];
		static::assertSame(['bild2.jpg', 'bild.jpg'], array_column($images, 'filename'));
		static::assertSame(['media-id-0000002', 'media-id-0000001'], array_column($images, 'media_id'));
		// the composer stores the plain file name, the urls are signed
		static::assertSame('checksum-image.jpg', $images[0]['file_name']);
		static::assertStringContainsString('sig=', $images[0]['original']);
		static::assertStringContainsString('sig=', $images[0]['thumbnail']);

		// the choice: the texts and only the pictures which were found, no video and no pdf of "Meer"
		$text = $this->lastRequestBody()['messages'][1]['content'];
		static::assertStringStartsWith("Texts: Sommerfest\nam Strand\nPictures: ", $text);
		$pictures = json_decode(substr($text, (int) strpos($text, '[{')), true);
		static::assertSame([
			['number' => 1, 'name' => 'bild.jpg', 'tags' => ['Strand'], 'description' => 'Sand und Sonne'],
			['number' => 2, 'name' => 'bild2.jpg', 'tags' => ['Wald'], 'description' => '']
		], $pictures);
	}

	public function testTheTermsAreAskedForTheTextsOnly(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->suggest(['texts' => " Sommerfest\n"], [self::answer('{"terms": ["Rakete"]}')]);

		static::assertSame('Sommerfest', $this->lastRequestBody()['messages'][1]['content']);
		static::assertSame(['type' => 'json_object'], $this->lastRequestBody()['response_format'] ?? null);
	}

	public function testWithoutMatchingPicturesTheAiIsNotAskedToChoose(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		// "Meer" finds only a video and a pdf
		$response = $this->suggest(['texts' => 'Weltraum'], [self::answer('{"terms": ["Rakete", "Meer"]}'), self::answer('{"pictures": [1]}')]);

		$this->assertError($response, $this->text('ai_no_image_found'));
		static::assertSame(1, $this->endpoint?->count());
	}

	public function testAChoiceOfNothingIsNothingFound(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->suggest(['texts' => 'Strand'], [self::answer('{"terms": ["Strand"]}'), self::answer('{"pictures": []}')]);

		$this->assertError($response, $this->text('ai_no_image_found'));
	}

	public function testAnAnswerWithoutTermsIsNoAnswer(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->suggest(['texts' => 'Strand'], [self::answer('no json')]);

		$this->assertError($response, $this->text('llm_no_answer', 'main'));
	}

	public function testWithoutAnLlmNothingIsSent(): void
	{
		$this->actingAs(2);

		$this->assertError($this->suggest(['texts' => 'Strand']), $this->text('llm_not_configured', 'main'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testTooLongTextsAreNotSent(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->suggest(['texts' => str_repeat('a', 3001)]), $this->text('llm_input_too_long', 'main'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testTextsAreNeeded(): void
	{
		$this->actingAs(2);

		$this->assertError($this->suggest(['texts' => "  \n "]), $this->text('ai_no_texts'));
		$this->assertError($this->suggest([]), $this->text('ai_no_texts'));
	}
}
