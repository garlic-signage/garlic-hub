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

use App\Framework\Llm\LlmClient;
use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmConnection;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Mediapool\Controller\ImageGenerationController;
use App\Shared\Llm\ImageGeneration;
use App\Shared\Llm\LlmTask;
use App\Shared\Llm\UserLlmService;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Imagick;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The image of the AI for the uploader /async/mediapool/generateImage with the AI of the user for images, which is
 * a fake endpoint here. Nothing is stored, the browser gets the image as data url.
 */
#[Group('acl')]
class ImageGenerationControllerTest extends ControllerTestCase
{
	private ?MockHandler $endpoint = null;

	/**
	 * @param array<string,mixed> $body
	 * @param list<Response> $answers
	 */
	private function generate(array $body, array $answers = []): ResponseInterface
	{
		$this->endpoint = new MockHandler($answers);
		$container = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)]), static fn(string $host): array => []));

		return $container->get(ImageGenerationController::class)->generate($this->request('POST', $body), $this->newResponse());
	}

	private static function answer(string $image): Response
	{
		return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['created' => 1, 'data' => [['b64_json' => base64_encode($image)]]]));
	}

	private static function png(): string
	{
		$imagick = new Imagick();
		$imagick->newImage(30, 20, 'green');
		$imagick->setImageFormat('png');

		return $imagick->getImageBlob();
	}

	private function withLlm(int $UID): void
	{
		$this->connection->executeStatement("INSERT INTO company_llm_tasks (company_id, task, mode) VALUES (1, ?, 'user_own')", [LlmTask::IMAGE_GENERATION]);
		$service = $this->container->get(UserLlmService::class);
		$service->saveConnection($UID, 0, ['name' => 'Ollama', 'api_type' => 'openai', 'base_url' => 'http://localhost:11434/v1', 'api_key' => '']);
		$service->saveTasks($UID, [LlmTask::IMAGE_GENERATION => ['connection_id' => $service->findConnections($UID)[0]['connection_id'], 'model' => 'flux-schnell']]);
	}

	private function text(string $key): string
	{
		return $this->container->get(Translator::class)->translate($key, 'main');
	}

	public function testTheImageComesBackAsDataUrlInTheFormatOfTheUser(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);
		$png = self::png();

		$response = $this->generate(['prompt' => ' Ein Strand ', 'format' => 'portrait'], [self::answer($png)]);

		$this->assertSuccess($response);
		static::assertSame('data:image/png;base64,' . base64_encode($png), $this->json($response)['image']);
		$sent = json_decode((string) $this->endpoint?->getLastRequest()?->getBody(), true);
		static::assertIsArray($sent);
		static::assertSame(['flux-schnell', 'Ein Strand', ImageGeneration::SIZES['portrait']], [$sent['model'], $sent['prompt'], $sent['size']]);
	}

	/**
	 * A local model takes often more than a minute for an image: it gets three, the texts one.
	 */
	public function testTheModelHasThreeMinutesForTheImage(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);
		$factory = new class(new Client(['handler' => HandlerStack::create(new MockHandler([self::answer(self::png())]))]), static fn(string $host): array => []) extends LlmClientFactory
		{
			public int $timeout = 0;

			public function create(LlmConnection $connection, int $timeout = 60): LlmClient
			{
				$this->timeout = $timeout;
				return parent::create($connection, $timeout);
			}
		};
		$container = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, $factory);

		$this->assertSuccess($container->get(ImageGenerationController::class)->generate($this->request('POST', ['prompt' => 'Strand']), $this->newResponse()));

		static::assertSame(ImageGeneration::TIMEOUT, $factory->timeout);
	}

	public function testAnUnknownFormatIsLandscape(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertSuccess($this->generate(['prompt' => 'Strand', 'format' => '9999x9999'], [self::answer(self::png())]));

		$sent = json_decode((string) $this->endpoint?->getLastRequest()?->getBody(), true);
		static::assertIsArray($sent);
		static::assertSame(ImageGeneration::SIZES['landscape'], $sent['size']);
	}

	/**
	 * Only an image goes into the browser and from there into the mediapool.
	 */
	public function testAnAnswerWhichIsNoImageIsNoAnswer(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->generate(['prompt' => 'Strand'], [self::answer('<html><script>alert(1)</script></html>')]), $this->text('llm_no_answer'));
	}

	public function testWithoutAnLlmNothingIsSent(): void
	{
		$this->actingAs(2);

		$this->assertError($this->generate(['prompt' => 'Strand']), $this->text('llm_not_configured'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	/**
	 * Without an AI the user learns that first, not that the description is missing.
	 */
	public function testWithoutAnLlmTheMissingAiComesFirst(): void
	{
		$this->actingAs(2);

		$this->assertError($this->generate(['prompt' => '']), $this->text('llm_not_configured'));
	}

	public function testAnEmptyOrTooLongDescriptionIsNotSent(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->generate(['prompt' => '   ']), $this->text('llm_prompt_missing'));
		$this->assertError($this->generate(['prompt' => str_repeat('a', ImageGeneration::MAX_PROMPT_LENGTH + 1)]), $this->text('llm_input_too_long'));
		$this->assertError($this->generate(['prompt' => 'Strand', 'negative_prompt' => str_repeat('a', ImageGeneration::MAX_PROMPT_LENGTH + 1)]), $this->text('llm_input_too_long'));
		static::assertNull($this->endpoint?->getLastRequest());
	}
}
