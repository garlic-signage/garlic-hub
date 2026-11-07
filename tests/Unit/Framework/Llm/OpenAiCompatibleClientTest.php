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

namespace Tests\Unit\Framework\Llm;

use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmConnection;
use App\Framework\Llm\LlmException;
use App\Framework\Llm\LlmMessage;
use App\Framework\Llm\LlmRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * The adapter of the OpenAI compatible API, created by the factory, against a fake endpoint: what goes out
 * and what the features get back.
 */
class OpenAiCompatibleClientTest extends TestCase
{
	private MockHandler $endpoint;

	/**
	 * @param list<Response|Throwable> $answers
	 */
	private function factory(array $answers): LlmClientFactory
	{
		$this->endpoint = new MockHandler($answers);

		return new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)]));
	}

	private static function answer(string $content): Response
	{
		return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
			'id' => 'chatcmpl-1', 'object' => 'chat.completion', 'created' => 1, 'model' => 'm',
			'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
			'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2]
		]));
	}

	private function sent(): RequestInterface
	{
		$request = $this->endpoint->getLastRequest();
		static::assertNotNull($request);

		return $request;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function sentBody(): array
	{
		$body = json_decode((string) $this->sent()->getBody(), true);
		static::assertIsArray($body);

		return $body;
	}

	private function assertLlmError(string $key, callable $call): void
	{
		try
		{
			$call();
			static::fail('No LlmException');
		}
		catch (LlmException $e)
		{
			static::assertSame($key, $e->getTranslationKey());
		}
	}

	#[Group('units')]
	public function testTheConversationGoesToTheChatCompletionsOfTheEndpoint(): void
	{
		$client = $this->factory([self::answer('Hallo Welt')])->create(new LlmConnection('openai', 'http://ollama:11434/v1/', 'qwen3', 'sk-1'));

		$answer = $client->complete(new LlmRequest('Be short.', [LlmMessage::user('Describe it', ['data:image/jpeg;base64,AAAA'])], true));

		static::assertSame('Hallo Welt', $answer);
		$request = $this->sent();
		static::assertSame('POST', $request->getMethod());
		static::assertSame('http://ollama:11434/v1/chat/completions', (string) $request->getUri());
		static::assertSame('Bearer sk-1', $request->getHeaderLine('Authorization'));
		static::assertSame([
			'model'           => 'qwen3',
			'messages'        => [
				['role' => 'system', 'content' => 'Be short.'],
				['role' => 'user', 'content' => [
					['type' => 'text', 'text' => 'Describe it'],
					['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,AAAA']]
				]]
			],
			'response_format' => ['type' => 'json_object']
		], $this->sentBody());
	}

	#[Group('units')]
	public function testALocalLlmWithoutKeyGetsNoAuthorization(): void
	{
		$client = $this->factory([self::answer('ok')])->create(new LlmConnection('openai', 'http://localhost:11434/v1', 'llama3'));

		$client->complete(new LlmRequest('', [LlmMessage::user('Hi')]));

		static::assertFalse($this->sent()->hasHeader('Authorization'));
		static::assertSame([['role' => 'user', 'content' => 'Hi']], $this->sentBody()['messages']);
	}

	/**
	 * @return array<string,array{Response|Throwable}>
	 */
	public static function failureProvider(): array
	{
		return [
			'wrong key'        => [new Response(401, ['Content-Type' => 'application/json'], '{"error":{"message":"internal secret","type":"auth"}}')],
			'an internal page' => [new Response(200, ['Content-Type' => 'text/html'], '<html>internal secret</html>')],
			'no connection'    => [new ConnectException('internal secret', new Request('POST', 'http://10.0.0.1'))],
		];
	}

	/**
	 * Whatever the address answers, the user gets only the translation key: no way to read an internal service.
	 */
	#[DataProvider('failureProvider')]
	#[Group('units')]
	public function testTheAnswerOfAFailingEndpointIsNotPassedOn(Response|Throwable $failure): void
	{
		$client = $this->factory([$failure])->create(new LlmConnection('openai', 'http://10.0.0.1/v1', 'm'));

		try
		{
			$client->complete(new LlmRequest('', [LlmMessage::user('Hi')]));
			static::fail('No LlmException');
		}
		catch (LlmException $e)
		{
			static::assertSame('llm_not_reachable', $e->getTranslationKey());
			static::assertStringNotContainsString('internal secret', $e->getMessage());
		}
	}

	#[Group('units')]
	public function testAnEmptyAnswerIsNoAnswer(): void
	{
		$client = $this->factory([self::answer("  \n")])->create(new LlmConnection('openai', 'https://api.openai.com/v1', 'gpt'));

		$this->assertLlmError('llm_no_answer', fn() => $client->complete(new LlmRequest('', [LlmMessage::user('Hi')])));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function invalidUrlProvider(): array
	{
		return [
			'file'      => ['file:///etc/passwd'],
			'ftp'       => ['ftp://example.com/v1'],
			'no scheme' => ['localhost:11434/v1'],
			'empty'     => [''],
		];
	}

	#[DataProvider('invalidUrlProvider')]
	#[Group('units')]
	public function testOnlyHttpAddressesAreCalled(string $url): void
	{
		$this->assertLlmError('llm_invalid_url', fn() => $this->factory([])->create(new LlmConnection('openai', $url, 'm')));
	}

	#[Group('units')]
	public function testAnUnknownApiHasNoAdapter(): void
	{
		$this->assertLlmError('llm_unknown_api', fn() => $this->factory([])->create(new LlmConnection('anthropic', 'https://api.anthropic.com', 'claude')));
	}

	/**
	 * Like MLX, a local server may leave out owned_by and lists a model twice.
	 */
	#[Group('units')]
	public function testTheModelsOfTheEndpointComeSortedAndOnce(): void
	{
		$list   = new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['object' => 'list', 'data' => [
			['id' => 'qwen3-30b', 'object' => 'model', 'created' => 1],
			['id' => 'Llama-3.2-3B', 'object' => 'model', 'created' => 1],
			['id' => 'qwen3-30b', 'object' => 'model', 'created' => 1, 'owned_by' => 'me']
		]]));
		$client = $this->factory([$list])->create(new LlmConnection('openai', 'http://192.168.10.251:8080/v1', '', 'sk-1'), LlmClientFactory::MODELS_TIMEOUT);

		static::assertSame(['Llama-3.2-3B', 'qwen3-30b'], $client->listModels());
		static::assertSame('GET', $this->sent()->getMethod());
		static::assertSame('http://192.168.10.251:8080/v1/models', (string) $this->sent()->getUri());
		static::assertSame('Bearer sk-1', $this->sent()->getHeaderLine('Authorization'));
	}

	/**
	 * @return array<string,array{Response|Throwable, string}>
	 */
	public static function modelsFailureProvider(): array
	{
		$request = new Request('GET', 'http://10.0.0.1/v1/models');

		return [
			'wrong key'        => [new Response(401, ['Content-Type' => 'application/json'], '{"error":{"message":"internal secret","type":"auth"}}'), 'llm_unauthorized'],
			'forbidden'        => [new Response(403, ['Content-Type' => 'text/plain'], 'internal secret'), 'llm_unauthorized'],
			'no models route'  => [new Response(404, ['Content-Type' => 'application/json'], '{"error":{"message":"internal secret","type":"not_found"}}'), 'llm_no_models'],
			'an internal page' => [new Response(200, ['Content-Type' => 'text/html'], '<html>internal secret</html>'), 'llm_no_models'],
			'refused'          => [new ConnectException('internal secret', $request), 'llm_connection_failed'],
			'too slow'         => [new ConnectTimeoutException('internal secret', $request), 'llm_timeout'],
		];
	}

	/**
	 * The user gets the reason for the form, but still nothing of what the address answers.
	 */
	#[DataProvider('modelsFailureProvider')]
	#[Group('units')]
	public function testAFailingModelListTellsOnlyTheReason(Response|Throwable $failure, string $reason): void
	{
		$client = $this->factory([$failure])->create(new LlmConnection('openai', 'http://10.0.0.1/v1', ''));

		try
		{
			$client->listModels();
			static::fail('No LlmException');
		}
		catch (LlmException $e)
		{
			static::assertSame($reason, $e->getTranslationKey());
			static::assertStringNotContainsString('internal secret', $e->getMessage());
		}
	}
}
