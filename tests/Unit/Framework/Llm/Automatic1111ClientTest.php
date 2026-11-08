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

use App\Framework\Llm\Automatic1111Client;
use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmConnection;
use App\Framework\Llm\LlmException;
use App\Framework\Llm\LlmMessage;
use App\Framework\Llm\LlmRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * The adapter of the web API of Automatic1111 (Draw Things, Forge, SD.Next), created by the factory, against a fake
 * endpoint: it makes images and lists the checkpoints, nothing else.
 */
class Automatic1111ClientTest extends TestCase
{
	private MockHandler $endpoint;

	/**
	 * @param list<Response|Throwable> $answers
	 */
	private function client(array $answers, string $model = 'flux_1_schnell_q5p.ckpt', string $apiKey = ''): Automatic1111Client
	{
		$this->endpoint = new MockHandler($answers);
		$client = new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)]))
			->create(new LlmConnection(LlmConnection::API_AUTOMATIC1111, 'http://drawthings:7860/', $model, $apiKey));
		static::assertInstanceOf(Automatic1111Client::class, $client);

		return $client;
	}

	private function sent(): RequestInterface
	{
		$request = $this->endpoint->getLastRequest();
		static::assertNotNull($request);

		return $request;
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
			static::assertStringNotContainsString('internal secret', $e->getMessage());
		}
	}

	#[Group('units')]
	public function testAnImageComesFromTxt2ImgWithTheSizeAndTheModel(): void
	{
		$client = $this->client([new Response(200, [], (string) json_encode(['images' => [base64_encode('PNG-BYTES')], 'info' => '{}']))]);

		static::assertSame('PNG-BYTES', $client->generateImage('A beach', '1536x1024'));

		$request = $this->sent();
		static::assertSame('POST', $request->getMethod());
		static::assertSame('http://drawthings:7860/sdapi/v1/txt2img', (string) $request->getUri());
		static::assertFalse($request->hasHeader('Authorization'));
		static::assertSame([
			'prompt' => 'A beach', 'width' => 1536, 'height' => 1024, 'batch_size' => 1, 'n_iter' => 1,
			'override_settings' => ['sd_model_checkpoint' => 'flux_1_schnell_q5p.ckpt'],
			'model' => 'flux_1_schnell_q5p.ckpt'
		], json_decode((string) $request->getBody(), true));
	}

	/**
	 * Automatic1111 protects its API with --api-auth user:password.
	 */
	#[Group('units')]
	public function testTheKeyIsSentAsBasicAuthentication(): void
	{
		$client = $this->client([new Response(200, [], (string) json_encode(['images' => [base64_encode('PNG')]]))], 'model', 'anna:secret');

		$client->generateImage('A beach', '1024x1024');

		static::assertSame('Basic ' . base64_encode('anna:secret'), $this->sent()->getHeaderLine('Authorization'));
	}

	#[Group('units')]
	public function testWhatShouldNotBeInTheImageIsTheNegativePrompt(): void
	{
		$client = $this->client([new Response(200, [], (string) json_encode(['images' => [base64_encode('PNG')]]))], '');

		$client->generateImage('A beach', '1024x1024', 'people, text');

		static::assertSame('people, text', json_decode((string) $this->sent()->getBody(), true)['negative_prompt'] ?? null);
	}

	/**
	 * Without a model the program takes the one it has loaded.
	 */
	#[Group('units')]
	public function testWithoutAModelNoneIsSent(): void
	{
		$client = $this->client([new Response(200, [], (string) json_encode(['images' => [base64_encode('PNG')]]))], '');

		$client->generateImage('A beach', '1024x1024');

		static::assertSame(['prompt' => 'A beach', 'width' => 1024, 'height' => 1024, 'batch_size' => 1, 'n_iter' => 1], json_decode((string) $this->sent()->getBody(), true));
	}

	#[Group('units')]
	public function testAnAnswerWithoutAnImageIsNoAnswer(): void
	{
		$client = $this->client([new Response(200, [], '{"images": []}')]);

		$this->assertLlmError('llm_no_answer', static fn() => $client->generateImage('A beach', '1024x1024'));
	}

	#[Group('units')]
	public function testAnErrorOfTheServerIsNotReachableAndNotShown(): void
	{
		$client = $this->client([new Response(500, [], 'internal secret')]);

		$this->assertLlmError('llm_not_reachable', static fn() => $client->generateImage('A beach', '1024x1024'));
	}

	#[Group('units')]
	public function testTheCheckpointsAreTheModels(): void
	{
		$client = $this->client([new Response(200, [], (string) json_encode([
			['title' => 'sdxl.safetensors [abc]', 'model_name' => 'sdxl'],
			['title' => 'flux.ckpt', 'model_name' => 'flux'],
			['title' => 'only a title']
		]))]);

		static::assertSame(['flux', 'only a title', 'sdxl'], $client->listModels());
		static::assertSame('http://drawthings:7860/sdapi/v1/sd-models', (string) $this->sent()->getUri());
	}

	#[Group('units')]
	public function testTheReasonWhyThereAreNoModels(): void
	{
		$this->assertLlmError('llm_unauthorized', fn() => $this->client([new Response(401, [], 'internal secret')])->listModels());
		$this->assertLlmError('llm_no_models', fn() => $this->client([new Response(404, [], 'internal secret')])->listModels());
		$this->assertLlmError('llm_no_models', fn() => $this->client([new Response(200, [], '[]')])->listModels());
		$this->assertLlmError('llm_connection_failed', fn() => $this->client([new ConnectException('refused', new Request('GET', 'http://drawthings:7860'))])->listModels());
	}

	#[Group('units')]
	public function testItAnswersNoTexts(): void
	{
		$client = $this->client([]);

		$this->assertLlmError('llm_api_images_only', static fn() => $client->complete(new LlmRequest('', [LlmMessage::user('Hello')])));
	}
}
