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

namespace Tests\Integration\Modules\Mediapool\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Llm\LlmClientFactory;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\System\ShellExecutor;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Controller\MediaTaggingController;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Mediapool\Services\MediaTaggingService;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\TaggingImage;
use App\Shared\Llm\LlmTask;
use App\Shared\Llm\MediaTagging;
use App\Shared\Llm\UserLlmService;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Imagick;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The suggestion of the description and the tags of a medium /async/mediapool/media/tags with the AI of the user,
 * which is a fake endpoint here. The originals are in a temporary storage.
 * Media: 1111 urlaub.jpg of anna in Fotos (8), 3333 geheim.png of bernd in Privat (11),
 * 4444 teilen.png of bernd in Geteilt (12, public).
 */
#[Group('acl')]
#[Group('io')]
class MediaTaggingControllerTest extends ControllerTestCase
{
	private const string PHOTO  = '11111111-1111-4111-8111-111111111111';
	private const string SHARED = '44444444-4444-4444-8444-444444444444';

	private MediaWorkspace $workspace;
	private ?MockHandler $endpoint = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$imagick = new Imagick();
		$imagick->newImage(2048, 1024, 'blue');
		$imagick->setImageFormat('jpeg');
		$this->workspace->store('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg', $imagick->getImageBlob());
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	/**
	 * @param list<Response> $answers
	 */
	private function suggest(string $mediaId, array $answers = []): ResponseInterface
	{
		$this->endpoint = new MockHandler($answers);
		$container = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)])));
		$service = new MediaTaggingService(
			$this->container->get(MediaService::class),
			new TaggingImage(
				$this->workspace->config,
				$this->workspace->filesystem,
				new ImagickFactory(),
				new Ffmpeg($this->workspace->config, $this->workspace->filesystem, new MediaProperties(), new ShellExecutor())
			),
			$container->get(MediaTagging::class),
			$this->container->get(UserSession::class)
		);
		$controller = new MediaTaggingController($service, $this->container->get(JsonResponseHandler::class), $this->container->get(Translator::class));

		return $controller->suggest($this->request('POST', ['media_id' => $mediaId]), $this->newResponse());
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
		$this->connection->executeStatement("INSERT INTO company_llm_tasks (company_id, task, mode) VALUES (1, ?, 'user_own')", [LlmTask::IMAGE_TAGGING]);
		$service = $this->container->get(UserLlmService::class);
		$service->saveConnection($UID, 0, ['name' => 'Ollama', 'api_type' => 'openai', 'base_url' => 'http://localhost:11434/v1', 'api_key' => '']);
		$service->saveTasks($UID, [LlmTask::IMAGE_TAGGING => ['connection_id' => $service->findConnections($UID)[0]['connection_id'], 'model' => 'gemma3']]);
	}

	private function text(string $key, string $module): string
	{
		return $this->container->get(Translator::class)->translate($key, $module);
	}

	public function testTheSuggestionComesBackAndNothingIsStored(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->suggest(self::PHOTO, [self::answer('<think>blue</think>{"description": "A blue area.", "tags": ["blue", " sky ", "", 7]}')]);

		$this->assertSuccess($response);
		$json = $this->json($response);
		static::assertSame('A blue area.', $json['description']);
		static::assertSame(['blue', 'sky'], $json['tags']);
		static::assertNull($this->databaseValue('SELECT tags FROM mediapool_files WHERE media_id = ?', self::PHOTO));

		$body = json_decode((string) $this->endpoint?->getLastRequest()?->getBody(), true);
		static::assertIsArray($body);
		static::assertSame('gemma3', $body['model']);
		static::assertSame(['type' => 'json_object'], $body['response_format']);
		static::assertStringContainsString('en_US', $body['messages'][0]['content']);
		// the original, made smaller for the AI
		$image = new Imagick();
		$image->readImageBlob((string) base64_decode(substr($body['messages'][1]['content'][1]['image_url']['url'], strlen('data:image/jpeg;base64,')), true));
		static::assertSame([1024, 512], [$image->getImageWidth(), $image->getImageHeight()]);
	}

	public function testWithoutAnLlmNothingIsSent(): void
	{
		$this->actingAs(2);

		$this->assertError($this->suggest(self::PHOTO), $this->text('llm_not_configured', 'main'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testAnAnswerWhichIsNoSuggestionIsAnError(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->suggest(self::PHOTO, [self::answer('Sorry, I can not see images.')]), $this->text('llm_no_answer', 'main'));
	}

	public function testAMediumWithoutItsFileIsNotTaggable(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);
		unlink($this->workspace->absolute($this->workspace->original('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg')));

		$this->assertError($this->suggest(self::PHOTO), $this->text('media_not_taggable', 'mediapool'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testAnAudioIsNotTaggable(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);
		$this->connection->executeStatement("UPDATE mediapool_files SET mimetype = 'audio/mpeg' WHERE media_id = ?", [self::PHOTO]);

		$this->assertError($this->suggest(self::PHOTO), $this->text('media_not_taggable', 'mediapool'));
	}

	/**
	 * The suggestion is for the edit dialog: a medium which the user may only see gets none.
	 */
	public function testAMediumWhichTheUserMayNotEditGetsNoSuggestion(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->expectException(AccessDeniedException::class);
		$this->suggest(self::SHARED);
	}

	public function testWithoutAMediumIdNothingIsSent(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->suggest(''), 'Media id is missing');
	}
}
