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
use App\Modules\Templates\Controller\TextCorrectionController;
use App\Shared\Llm\LlmTask;
use App\Shared\Llm\TextCorrection;
use App\Shared\Llm\UserLlmService;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The text correction of the composer /async/templates/correct-text with the own LLM of the user, which is
 * a fake endpoint here.
 */
#[Group('acl')]
class TextCorrectionControllerTest extends TemplatesControllerTestCase
{
	private ?MockHandler $endpoint = null;

	/**
	 * @param list<Response> $answers
	 */
	private function correct(string $text, array $answers = []): ResponseInterface
	{
		$this->endpoint = new MockHandler($answers);
		$container = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)])));

		return $container->get(TextCorrectionController::class)->correct($this->request('POST', '/async/templates/correct-text', ['text' => $text]), $this->newResponse());
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

	public function testTheTextComesBackCorrected(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$response = $this->correct("Wilkommen im\nBüro", [self::answer("<think>a typo</think>\nWillkommen im\nBüro")]);

		$this->assertSuccess($response);
		static::assertSame("Willkommen im\nBüro", $this->json($response)['text']);
		$body = json_decode((string) $this->endpoint?->getLastRequest()?->getBody(), true);
		static::assertIsArray($body);
		static::assertSame('qwen3', $body['model']);
		static::assertSame(['role' => 'user', 'content' => "Wilkommen im\nBüro"], $body['messages'][1]);
	}

	public function testWithoutAnLlmNothingIsSent(): void
	{
		$this->actingAs(3);

		$this->assertError($this->correct('Text'), $this->text('llm_not_configured', 'main'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testATooLongTextIsNotSent(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->correct(str_repeat('a', TextCorrection::MAX_LENGTH + 1)), $this->text('llm_text_too_long', 'main'));
		static::assertNull($this->endpoint?->getLastRequest());
	}

	public function testAFailingLlmIsAnError(): void
	{
		$this->actingAs(2);
		$this->withLlm(2);

		$this->assertError($this->correct('Text', [new Response(500, [], 'internal')]), $this->text('llm_not_reachable', 'main'));
	}
}
