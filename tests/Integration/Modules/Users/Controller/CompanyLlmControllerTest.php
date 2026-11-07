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

namespace Tests\Integration\Modules\Users\Controller;

use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Users\Controller\CompanyLlmController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The page of the AI of the company /users/llm: only the module admin of users manages it, for his company.
 * Users: 1 admin (module admin of all modules), 2 anna without rights, both of the company 1.
 */
#[Group('acl')]
class CompanyLlmControllerTest extends ContainerTestCase
{
	private CompanyLlmController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->controller = $this->container->get(CompanyLlmController::class);
	}

	/**
	 * @param array<string,string> $post
	 */
	private function request(string $path, array $post = []): ServerRequestInterface
	{
		return new ServerRequestFactory()->createServerRequest('POST', $path)->withParsedBody($post);
	}

	/**
	 * @param array<string,string> $post
	 */
	private function storeTasks(array $post): ResponseInterface
	{
		return $this->controller->storeTasks($this->request('/users/llm/tasks', $post), new Response());
	}

	private function addProvider(int $companyId = 1): string
	{
		$this->connection->executeStatement("INSERT INTO company_llm_connections (company_id, name, base_url) VALUES (?, 'Ollama', 'http://localhost:11434/v1')", [$companyId]);

		return (string) $this->connection->lastInsertId();
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

	private function text(string $key): string
	{
		return $this->container->get(Translator::class)->translate($key, 'main');
	}

	public function testTheAdminSetsUpAProviderAndDecidesForEveryTask(): void
	{
		$this->actingAs(1);
		$this->controller->storeConnection($this->request('/users/llm/connection', ['connection_id' => '0', 'name' => 'OpenAI', 'api_type' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-1']), new Response());
		$connectionId = (string) $this->connection->fetchOne('SELECT connection_id FROM company_llm_connections WHERE company_id = 1');

		$response = $this->storeTasks([
			'mode_text'  => 'company_admins', 'connection_text' => $connectionId, 'model_text' => 'gpt-5',
			'mode_image_tagging'    => 'user_own', 'connection_image_tagging' => $connectionId, 'model_image_tagging' => 'gpt-5',
			'mode_image_generation' => 'disabled'
		]);

		static::assertSame('/users/llm', $response->getHeaderLine('Location'));
		static::assertSame([$this->text('llm_connection_saved'), $this->text('llm_tasks_saved')], $this->flash('success'));
		static::assertSame(
			[['image_generation', 'disabled', null, ''], ['image_tagging', 'user_own', null, ''], ['text', 'company_admins', (int) $connectionId, 'gpt-5']],
			array_map(static fn(array $row): array => [$row[0], $row[1], $row[2] === null ? null : (int) $row[2], $row[3]], $this->connection->fetchAllNumeric('SELECT task, mode, connection_id, model FROM company_llm_tasks ORDER BY task'))
		);
		$page = $this->controller->show(new ServerRequestFactory()->createServerRequest('GET', '/users/llm'), new Response());
		static::assertInstanceOf(PageResponse::class, $page);
		$modes = $page->getTemplateData()['tasks'][0]['company']['modes'];
		static::assertSame(['company_admins'], array_column(array_filter($modes, static fn(array $option): bool => $option['is_selected']), 'VALUE'));
	}

	public function testTheAiOfTheCompanyNeedsAProviderAndAWrongModeIsNotStored(): void
	{
		$this->actingAs(1);

		$this->storeTasks(['mode_text' => 'company_all', 'connection_text' => '0', 'mode_image_tagging' => 'everybody']);

		static::assertSame([$this->text('llm_missing_connection'), $this->text('llm_invalid_mode')], $this->flash('error'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM company_llm_tasks'));
	}

	/**
	 * In Enterprise every customer is a company: the key of another one must not be used.
	 */
	public function testTheProviderOfAnotherCompanyIsNotUsable(): void
	{
		$this->actingAs(1);
		$foreign = $this->addProvider(2);

		$this->storeTasks(['mode_text' => 'company_all', 'connection_text' => $foreign, 'model_text' => 'qwen3']);

		static::assertSame([$this->text('llm_unknown_connection')], $this->flash('error'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM company_llm_tasks'));
	}

	public function testAUserWithoutTheRightChangesNothing(): void
	{
		$this->actingAs(2);
		$connectionId = $this->addProvider();

		$stored  = $this->controller->storeConnection($this->request('/users/llm/connection', ['connection_id' => $connectionId, 'api_type' => 'openai', 'base_url' => 'http://evil.test/v1']), new Response());
		$deleted = $this->controller->deleteConnection($this->request('/users/llm/connection/delete', ['connection_id' => $connectionId]), new Response());
		$tasks   = $this->storeTasks(['mode_text' => 'company_all', 'connection_text' => $connectionId, 'model_text' => 'x']);

		static::assertSame(['/', '/', '/'], [$stored->getHeaderLine('Location'), $deleted->getHeaderLine('Location'), $tasks->getHeaderLine('Location')]);
		static::assertSame('http://localhost:11434/v1', $this->connection->fetchOne('SELECT base_url FROM company_llm_connections'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM company_llm_tasks'));
	}

	/**
	 * Otherwise he could send the stored key of the company to an address of his own.
	 */
	public function testAUserWithoutTheRightLoadsNoModels(): void
	{
		$this->actingAs(2);

		$response = $this->controller->models($this->request('/users/llm/models', ['connection_id' => $this->addProvider(), 'api_type' => 'openai', 'base_url' => 'http://evil.test/v1', 'api_key' => '']), new Response());

		static::assertSame(403, $response->getStatusCode());
		static::assertFalse(json_decode((string) $response->getBody(), true)['success'] ?? true);
	}
}
