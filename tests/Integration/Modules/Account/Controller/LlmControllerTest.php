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

namespace Tests\Integration\Modules\Account\Controller;

use App\Framework\Localization\Translate\Translator;
use App\Modules\Account\Controller\LlmController;
use App\Modules\Account\Helper\Llm\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The tab of the own LLM in the account: store, errors, delete. The key never comes back to the page.
 */
#[Group('auth')]
class LlmControllerTest extends ContainerTestCase
{
	private LlmController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->actingAs(2);
		$this->controller = $this->container->get(LlmController::class);
	}

	/**
	 * @param array<string,string> $post
	 */
	private function store(array $post): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest('POST', '/account/llm')->withParsedBody($post);

		return $this->controller->store($request, new Response());
	}

	/**
	 * @return list<string>
	 */
	private function flash(string $type): array
	{
		/** @var array<string,list<string>> $flash */
		$flash = $_SESSION['slimFlash'] ?? [];

		return $flash[$type] ?? [];
	}

	private function assertBackToTab(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/account/settings#llm', $response->getHeaderLine('Location'));
	}

	public function testTheLlmIsStoredAndTheTabShowsItWithoutTheKey(): void
	{
		$response = $this->store(['api_type' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'model' => 'gpt-5', 'api_key' => 'sk-secret']);

		$this->assertBackToTab($response);
		static::assertSame([$this->container->get(Translator::class)->translate('llm_saved', 'account')], $this->flash('success'));
		$tab = $this->container->get(Orchestrator::class)->buildTab();
		static::assertSame(['https://api.openai.com/v1', 'gpt-5'], [$tab['BASE_URL'], $tab['MODEL']]);
		static::assertStringNotContainsString('sk-secret', (string) json_encode($tab));
		static::assertNotSame([], $tab['can_delete']);
	}

	public function testWhileTheCompanyHasAnLlmTheTabSaysSoInsteadOfTheForm(): void
	{
		$this->connection->executeStatement("INSERT INTO company_llm (company_id, base_url, model) VALUES (1, 'http://localhost:11434/v1', 'a')");

		$tab = $this->container->get(Orchestrator::class)->buildTab();

		static::assertSame($this->container->get(Translator::class)->translate('llm_of_company', 'account'), $tab['company_llm']['LANG_COMPANY_LLM']);
	}

	public function testWrongInputShowsTheErrors(): void
	{
		$response = $this->store(['api_type' => 'openai', 'base_url' => 'ftp://example.com', 'model' => 'gpt-5']);

		$this->assertBackToTab($response);
		static::assertSame([$this->container->get(Translator::class)->translate('llm_invalid_url', 'main')], $this->flash('error'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_llm'));
	}

	public function testDeleteRemovesTheLlmOfTheUserOnly(): void
	{
		$this->connection->executeStatement("INSERT INTO user_llm (UID, base_url, model) VALUES (2, 'http://localhost:11434/v1', 'a'), (3, 'http://localhost:11434/v1', 'b')");

		$this->assertBackToTab($this->controller->delete(new ServerRequestFactory()->createServerRequest('POST', '/account/llm/delete'), new Response()));

		static::assertSame([3], array_map('intval', $this->connection->fetchFirstColumn('SELECT UID FROM user_llm')));
	}
}
