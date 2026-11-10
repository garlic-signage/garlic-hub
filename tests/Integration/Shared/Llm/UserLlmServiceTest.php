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

namespace Tests\Integration\Shared\Llm;

use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmException;
use App\Shared\Llm\LlmTask;
use App\Shared\Llm\UserLlmService;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The own AI of a user in the database: the providers with their key, which is stored encrypted and goes only to the
 * address it was entered for, and the model of a provider for each task.
 */
#[Group('auth')]
class UserLlmServiceTest extends ContainerTestCase
{
	private UserLlmService $service;
	private MockHandler $endpoint;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->endpoint = new MockHandler();
		$container      = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)]), static fn(string $host): array => []));
		$this->service = $container->get(UserLlmService::class);
	}

	/**
	 * @return list<string>
	 */
	private function findModels(int $connectionId, string $baseUrl, string $apiKey = ''): array
	{
		$this->endpoint->append(new Response(200, ['Content-Type' => 'application/json'], '{"object":"list","data":[{"id":"gpt-5","object":"model"}]}'));

		return $this->service->findModels(2, ['connection_id' => $connectionId, 'api_type' => 'openai', 'base_url' => $baseUrl, 'api_key' => $apiKey]);
	}

	private function sentAuthorization(): string
	{
		return (string) $this->endpoint->getLastRequest()?->getHeaderLine('Authorization');
	}

	/**
	 * @return array{name:string, api_type:string, base_url:string, api_key:string}
	 */
	private function input(string $baseUrl = 'https://api.openai.com/v1/', string $apiKey = 'sk-secret', string $name = 'OpenAI'): array
	{
		return ['name' => $name, 'api_type' => 'openai', 'base_url' => $baseUrl, 'api_key' => $apiKey];
	}

	/**
	 * @return int the new provider of anna
	 */
	private function addProvider(): int
	{
		static::assertSame([], $this->service->saveConnection(2, 0, $this->input()));

		return (int) $this->connection->fetchOne('SELECT MAX(connection_id) FROM user_llm_connections');
	}

	private function storedKey(int $connectionId): string
	{
		$this->service->saveTasks(2, [LlmTask::TEXT => ['connection_id' => $connectionId, 'model' => 'gpt-5']]);

		return (string) $this->service->findConnectionOfTask(2, LlmTask::TEXT)?->apiKey;
	}

	public function testTheKeyIsStoredEncryptedAndComesBackForTheTask(): void
	{
		$connectionId = $this->addProvider();

		static::assertStringNotContainsString('sk-secret', (string) $this->connection->fetchOne('SELECT api_key FROM user_llm_connections'));
		static::assertSame([['connection_id' => $connectionId, 'name' => 'OpenAI', 'api_type' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'has_key' => true]], $this->service->findConnections(2));
		static::assertSame('sk-secret', $this->storedKey($connectionId));
		static::assertSame('gpt-5', $this->service->findConnectionOfTask(2, LlmTask::TEXT)?->model);
		static::assertNull($this->service->findConnectionOfTask(3, LlmTask::TEXT));
	}

	public function testAProviderWithoutNameIsCalledLikeItsHost(): void
	{
		$this->service->saveConnection(2, 0, $this->input('http://localhost:11434/v1', '', ''));

		static::assertSame('localhost', $this->service->findConnections(2)[0]['name']);
	}

	public function testAnEmptyKeyKeepsTheStoredOneForTheSameAddress(): void
	{
		$connectionId = $this->addProvider();

		$this->service->saveConnection(2, $connectionId, $this->input(apiKey: '', name: 'Renamed'));

		static::assertSame('Renamed', $this->service->findConnections(2)[0]['name']);
		static::assertSame('sk-secret', $this->storedKey($connectionId));
	}

	/**
	 * Otherwise the key of the provider would go to the new address, e.g. a server of somebody else.
	 */
	public function testANewAddressWithoutKeyDropsTheStoredOne(): void
	{
		$connectionId = $this->addProvider();

		$this->service->saveConnection(2, $connectionId, $this->input('http://localhost:11434/v1', ''));

		static::assertSame('', $this->storedKey($connectionId));
		static::assertFalse($this->service->findConnections(2)[0]['has_key']);
	}

	/**
	 * The form does not show the stored key: an empty field means it, like on saving.
	 */
	public function testTheTestOfAProviderUsesTheStoredKeyOfTheSameAddress(): void
	{
		$connectionId = $this->addProvider();

		static::assertSame(['gpt-5'], $this->findModels($connectionId, 'https://api.openai.com/v1/'));
		static::assertSame('Bearer sk-secret', $this->sentAuthorization());

		$this->findModels($connectionId, 'https://api.openai.com/v1', 'sk-typed');
		static::assertSame('Bearer sk-typed', $this->sentAuthorization());
	}

	public function testTheStoredKeyDoesNotGoToAnotherAddress(): void
	{
		$connectionId = $this->addProvider();

		$this->findModels($connectionId, 'http://evil.test/v1');

		static::assertSame('', $this->sentAuthorization());
		static::assertSame('http://evil.test/v1/models', (string) $this->endpoint->getLastRequest()?->getUri());
	}

	/**
	 * The selection of a model in the row of a task names only the provider.
	 */
	public function testTheModelsOfAStoredProvider(): void
	{
		$connectionId = $this->addProvider();

		static::assertSame(['gpt-5'], $this->findModels($connectionId, ''));
		static::assertSame('https://api.openai.com/v1/models', (string) $this->endpoint->getLastRequest()?->getUri());
		static::assertSame('Bearer sk-secret', $this->sentAuthorization());
	}

	/**
	 * Otherwise a user could send the key of somebody else to an address of his own or work with it.
	 */
	public function testTheProviderOfAnotherUserIsNotUsable(): void
	{
		$this->service->saveConnection(3, 0, $this->input());
		$foreign = (int) $this->connection->fetchOne('SELECT connection_id FROM user_llm_connections WHERE UID = 3');

		static::assertSame(['llm_unknown_connection'], $this->service->saveTasks(2, [LlmTask::TEXT => ['connection_id' => $foreign, 'model' => 'gpt-5']]));
		static::assertSame(['llm_unknown_connection'], $this->service->saveConnection(2, $foreign, $this->input('http://evil.test/v1', '')));
		$this->findModels($foreign, 'https://api.openai.com/v1');
		static::assertSame('', $this->sentAuthorization());

		$this->expectException(LlmException::class);
		$this->findModels($foreign, '');
	}

	public function testATaskWithoutProviderIsOffAndADeletedProviderTakesItsTasks(): void
	{
		$connectionId = $this->addProvider();
		$this->service->saveTasks(2, [LlmTask::TEXT => ['connection_id' => $connectionId, 'model' => 'gpt-5'], LlmTask::IMAGE_TAGGING => ['connection_id' => $connectionId, 'model' => 'gpt-5']]);

		$this->service->saveTasks(2, [LlmTask::TEXT => ['connection_id' => 0, 'model' => '']]);
		static::assertSame([LlmTask::IMAGE_TAGGING], array_keys($this->service->findTasks(2)));

		$this->service->deleteConnection(2, $connectionId);
		static::assertSame([], $this->service->findTasks(2));
	}

	/**
	 * A provider of Automatic1111 (Draw Things) makes images only: it serves no other task, also not after its type
	 * was changed while a task of texts used it.
	 */
	public function testAProviderOfAutomatic1111ServesOnlyTheImages(): void
	{
		$this->service->saveConnection(2, 0, ['name' => 'Draw Things', 'api_type' => 'automatic1111', 'base_url' => 'http://localhost:7860', 'api_key' => '']);
		$drawThings = (int) $this->connection->fetchOne('SELECT MAX(connection_id) FROM user_llm_connections');

		static::assertSame(['llm_api_images_only'], $this->service->saveTasks(2, [
			LlmTask::TEXT             => ['connection_id' => $drawThings, 'model' => 'flux'],
			LlmTask::IMAGE_GENERATION => ['connection_id' => $drawThings, 'model' => 'flux']
		]));
		static::assertSame([LlmTask::IMAGE_GENERATION], array_keys($this->service->findTasks(2)));
		static::assertTrue($this->service->hasModelForTask(2, LlmTask::IMAGE_GENERATION));

		// the model which the program has loaded: none is needed, unlike for the OpenAI compatible ones
		static::assertSame([], $this->service->saveTasks(2, [LlmTask::IMAGE_GENERATION => ['connection_id' => $drawThings, 'model' => ' ']]));
		static::assertTrue($this->service->hasModelForTask(2, LlmTask::IMAGE_GENERATION));
		static::assertSame('', $this->service->findConnectionOfTask(2, LlmTask::IMAGE_GENERATION)?->model);

		$openAi = $this->addProvider();
		static::assertSame(['llm_invalid_model'], $this->service->saveTasks(2, [LlmTask::IMAGE_GENERATION => ['connection_id' => $openAi, 'model' => '']]));
		$this->service->saveTasks(2, [LlmTask::TEXT => ['connection_id' => $openAi, 'model' => 'gpt-5']]);
		$this->connection->executeStatement("UPDATE user_llm_connections SET api_type = 'automatic1111' WHERE connection_id = ?", [$openAi]);
		static::assertFalse($this->service->hasModelForTask(2, LlmTask::TEXT));
	}

	public function testWrongInputIsNotStored(): void
	{
		$errors = $this->service->saveConnection(2, 0, ['name' => str_repeat('a', 101), 'api_type' => 'other', 'base_url' => 'file:///etc/passwd', 'api_key' => '']);
		$connectionId = $this->addProvider();

		static::assertSame(['llm_unknown_api', 'llm_invalid_url', 'llm_invalid_name'], $errors);
		static::assertSame(['llm_invalid_model', 'llm_unknown_task'], $this->service->saveTasks(2, [
			LlmTask::TEXT => ['connection_id' => $connectionId, 'model' => ' '],
			'chat'                   => ['connection_id' => $connectionId, 'model' => 'gpt-5']
		]));
		static::assertCount(1, $this->service->findConnections(2));
		static::assertSame([], $this->service->findTasks(2));
	}

	public function testTheInputIsTrimmedAndTheLimitsCountCharacters(): void
	{
		$url = 'https://llm.example.test/' . str_repeat('v', 255 - strlen('https://llm.example.test/'));
		static::assertSame([], $this->service->saveConnection(2, 0, ['name' => ' ' . str_repeat('ü', 100) . ' ', 'api_type' => ' openai ', 'base_url' => ' ' . $url . '/ ', 'api_key' => ' sk-secret ']));
		$connectionId = (int) $this->connection->fetchOne('SELECT MAX(connection_id) FROM user_llm_connections');

		static::assertSame(['connection_id' => $connectionId, 'name' => str_repeat('ü', 100), 'api_type' => 'openai', 'base_url' => $url, 'has_key' => true], $this->service->findConnections(2)[0]);
		static::assertSame('sk-secret', $this->storedKey($connectionId));
		static::assertSame(['llm_invalid_url'], $this->service->saveConnection(2, 0, $this->input($url . 'v')));

		static::assertSame([], $this->service->saveTasks(2, [LlmTask::TEXT => ['connection_id' => $connectionId, 'model' => ' ' . str_repeat('m', 100) . ' ']]));
		static::assertSame(str_repeat('m', 100), $this->service->findConnectionOfTask(2, LlmTask::TEXT)?->model);
		static::assertSame(['llm_invalid_model'], $this->service->saveTasks(2, [LlmTask::TEXT => ['connection_id' => $connectionId, 'model' => str_repeat('m', 101)]]));
	}

	public function testEveryProviderOfTheUserIsListed(): void
	{
		$this->addProvider();
		$this->service->saveConnection(2, 0, $this->input('http://localhost:11434/v1', '', 'Ollama'));

		static::assertSame(['Ollama', 'OpenAI'], array_column($this->service->findConnections(2), 'name'));
	}

	/**
	 * The form of a stored provider sends its address with spaces and an empty key field: the stored key goes along.
	 */
	public function testTheTestOfAStoredProviderTrimsTheForm(): void
	{
		$connectionId = $this->addProvider();
		$this->endpoint->append(new Response(200, ['Content-Type' => 'application/json'], '{"object":"list","data":[{"id":"gpt-5","object":"model"}]}'));

		static::assertSame(['gpt-5'], $this->service->findModels(2, ['connection_id' => $connectionId, 'api_type' => ' openai ', 'base_url' => ' https://api.openai.com/v1/ ', 'api_key' => ' ']));
		static::assertSame('Bearer sk-secret', $this->sentAuthorization());
	}

	public function testEveryErrorIsReportedOnce(): void
	{
		$connectionId = $this->addProvider();

		static::assertSame(['llm_invalid_model', 'llm_unknown_task'], $this->service->saveTasks(2, [
			LlmTask::TEXT          => ['connection_id' => $connectionId, 'model' => ''],
			LlmTask::IMAGE_TAGGING => ['connection_id' => $connectionId, 'model' => ''],
			'chat'                 => ['connection_id' => $connectionId, 'model' => 'gpt-5']
		]));
	}
}
