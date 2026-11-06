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

use App\Shared\Llm\UserLlmService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The own LLM of a user in the database: validation and the key, which is stored encrypted and goes only
 * to the address it was entered for.
 */
#[Group('auth')]
class UserLlmServiceTest extends ContainerTestCase
{
	private UserLlmService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->service = $this->container->get(UserLlmService::class);
	}

	/**
	 * @return array{api_type:string, base_url:string, model:string, api_key:string}
	 */
	private function input(string $baseUrl = 'https://api.openai.com/v1/', string $apiKey = 'sk-secret', string $model = 'gpt-5'): array
	{
		return ['api_type' => 'openai', 'base_url' => $baseUrl, 'model' => $model, 'api_key' => $apiKey];
	}

	public function testTheKeyIsStoredEncryptedAndComesBackForTheConnection(): void
	{
		static::assertSame([], $this->service->save(2, $this->input()));

		static::assertStringNotContainsString('sk-secret', (string) $this->connection->fetchOne('SELECT api_key FROM user_llm WHERE UID = 2'));
		static::assertSame(['api_type' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'model' => 'gpt-5', 'has_key' => true], $this->service->findSettings(2));
		$connection = $this->service->findConnection(2);
		static::assertNotNull($connection);
		static::assertSame('sk-secret', $connection->apiKey);
		static::assertNull($this->service->findConnection(3));
	}

	public function testAnEmptyKeyKeepsTheStoredOneForTheSameAddress(): void
	{
		$this->service->save(2, $this->input());

		$this->service->save(2, $this->input(apiKey: '', model: 'gpt-5-mini'));

		$connection = $this->service->findConnection(2);
		static::assertNotNull($connection);
		static::assertSame(['gpt-5-mini', 'sk-secret'], [$connection->model, $connection->apiKey]);
	}

	/**
	 * Otherwise the key of the provider would go to the new address, e.g. a server of somebody else.
	 */
	public function testANewAddressWithoutKeyDropsTheStoredOne(): void
	{
		$this->service->save(2, $this->input());

		$this->service->save(2, $this->input('http://localhost:11434/v1', ''));

		static::assertSame('', $this->service->findConnection(2)?->apiKey);
		static::assertFalse($this->service->findSettings(2)['has_key'] ?? true);
	}

	public function testWrongInputIsNotStored(): void
	{
		$errors = $this->service->save(2, ['api_type' => 'other', 'base_url' => 'file:///etc/passwd', 'model' => ' ', 'api_key' => '']);

		static::assertSame(['llm_unknown_api', 'llm_invalid_url', 'llm_invalid_model'], $errors);
		static::assertNull($this->service->findSettings(2));
	}
}
