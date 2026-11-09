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
use App\Shared\Llm\CompanyLlmService;
use App\Shared\Llm\LlmTask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The tab of the own AI in the account: providers, tasks, errors, delete. The key never comes back to the page.
 * Anna (2) sets up only the tasks her company leaves to her.
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
	private function request(string $path, array $post = []): ServerRequestInterface
	{
		return new ServerRequestFactory()->createServerRequest('POST', $path)->withParsedBody($post);
	}

	/**
	 * @param array<string,string> $post
	 */
	private function storeConnection(array $post): ResponseInterface
	{
		return $this->controller->storeConnection($this->request('/account/llm/connection', $post), new Response());
	}

	private function companyDecides(string $task, string $mode): void
	{
		$this->connection->executeStatement('INSERT INTO company_llm_tasks (company_id, task, mode) VALUES (1, ?, ?)', [$task, $mode]);
	}

	private function addOwnProvider(int $UID): int
	{
		$this->connection->executeStatement("INSERT INTO user_llm_connections (UID, name, base_url) VALUES (?, 'Ollama', 'http://localhost:11434/v1')", [$UID]);

		return (int) $this->connection->lastInsertId();
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

	private function text(string $key, string $module = 'main'): string
	{
		return $this->container->get(Translator::class)->translate($key, $module);
	}

	private function assertBackToTab(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/account/settings#llm', $response->getHeaderLine('Location'));
	}

	public function testTheProviderIsStoredAndTheTabShowsItWithoutTheKey(): void
	{
		$this->companyDecides(LlmTask::TEXT, CompanyLlmService::MODE_USER_OWN);
		$response = $this->storeConnection(['connection_id' => '0', 'name' => 'OpenAI', 'api_type' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-secret']);

		$this->assertBackToTab($response);
		static::assertSame([$this->text('llm_connection_saved')], $this->flash('success'));
		$tab = $this->container->get(Orchestrator::class)->buildTab();
		static::assertSame(['OpenAI', 'https://api.openai.com/v1', true], [$tab['connections'][0]['NAME'], $tab['connections'][0]['BASE_URL'], $tab['connections'][0]['can_delete']]);
		static::assertStringNotContainsString('sk-secret', (string) json_encode($tab));
	}

	/**
	 * The task the company provides is shown without a choice, the one which is off not at all.
	 */
	public function testTheTabShowsTheTasksAsTheCompanyDecided(): void
	{
		$this->companyDecides(LlmTask::TEXT, CompanyLlmService::MODE_COMPANY_ALL);
		$this->companyDecides(LlmTask::IMAGE_TAGGING, CompanyLlmService::MODE_USER_OWN);

		$tasks = $this->container->get(Orchestrator::class)->buildTab()['tasks'];

		static::assertSame([LlmTask::TEXT, LlmTask::IMAGE_TAGGING], array_column($tasks, 'TASK'));
		static::assertSame([$this->text('llm_task_of_company')], [$tasks[0]['locked']['LANG_LOCKED'] ?? '']);
		static::assertSame([[], []], [$tasks[0]['editable'], $tasks[1]['locked']]);
	}

	public function testWithoutReleasedTasksTheTabSaysSo(): void
	{
		$tab = $this->container->get(Orchestrator::class)->buildTab();

		static::assertFalse($tab['has_tasks']);
		static::assertSame($this->text('llm_no_tasks', 'account'), $tab['LANG_NO_TASKS']);
	}

	/**
	 * A task the company provides or switched off is not stored, even when the request has it.
	 */
	public function testOnlyTheTasksOfTheUserAreStored(): void
	{
		$this->companyDecides(LlmTask::TEXT, CompanyLlmService::MODE_COMPANY_ALL);
		$this->companyDecides(LlmTask::IMAGE_TAGGING, CompanyLlmService::MODE_USER_OWN);
		$connectionId = (string) $this->addOwnProvider(2);

		$response = $this->controller->storeTasks($this->request('/account/llm/tasks', [
			'connection_text'  => $connectionId, 'model_text'  => 'qwen3',
			'connection_image_tagging'    => $connectionId, 'model_image_tagging'    => 'qwen3-vl',
			'connection_image_generation' => $connectionId, 'model_image_generation' => 'flux'
		]), new Response());

		$this->assertBackToTab($response);
		static::assertSame([$this->text('llm_tasks_saved')], $this->flash('success'));
		static::assertSame([[LlmTask::IMAGE_TAGGING, 'qwen3-vl']], $this->connection->fetchAllNumeric('SELECT task, model FROM user_llm_tasks'));
	}

	public function testWrongInputShowsTheErrors(): void
	{
		$this->companyDecides(LlmTask::TEXT, CompanyLlmService::MODE_USER_OWN);
		$response = $this->storeConnection(['connection_id' => '0', 'api_type' => 'openai', 'base_url' => 'ftp://example.com']);

		$this->assertBackToTab($response);
		static::assertSame([$this->text('llm_invalid_url')], $this->flash('error'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_llm_connections'));
	}

	public function testTheModelsOfAWrongAddressComeAsJsonError(): void
	{
		$this->companyDecides(LlmTask::TEXT, CompanyLlmService::MODE_USER_OWN);
		$response = $this->controller->models($this->request('/account/llm/models', ['connection_id' => '0', 'api_type' => 'openai', 'base_url' => 'file:///etc/passwd', 'api_key' => '']), new Response());

		static::assertSame(400, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => $this->text('llm_invalid_url')], json_decode((string) $response->getBody(), true));
	}

	/**
	 * Without a task for own models the user could not use a provider: the server asks no address for him.
	 *
	 * @return array<string,array{string}>
	 */
	public static function modesWithoutOwnModels(): array
	{
		return [
			'no decision'         => [''],
			'company for all'     => [CompanyLlmService::MODE_COMPANY_ALL],
			'company for admins'  => [CompanyLlmService::MODE_COMPANY_ADMINS],
			'off'                 => [CompanyLlmService::MODE_DISABLED]
		];
	}

	#[DataProvider('modesWithoutOwnModels')]
	public function testWithoutOwnModelsNoProviderIsStoredAndNoAddressAsked(string $mode): void
	{
		if ($mode !== '')
			$this->companyDecides(LlmTask::TEXT, $mode);

		$this->assertBackToTab($this->storeConnection(['connection_id' => '0', 'name' => 'Intern', 'api_type' => 'openai', 'base_url' => 'http://10.0.0.1/v1']));
		static::assertSame([$this->text('llm_own_not_released')], $this->flash('error'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_llm_connections'));

		$response = $this->controller->models($this->request('/account/llm/models', ['connection_id' => '0', 'api_type' => 'openai', 'base_url' => 'http://10.0.0.1/v1', 'api_key' => '']), new Response());
		static::assertSame(['success' => false, 'error_message' => $this->text('llm_own_not_released')], json_decode((string) $response->getBody(), true));
	}

	public function testTheModelsOfALinkLocalAddressAreNotAsked(): void
	{
		$this->companyDecides(LlmTask::TEXT, CompanyLlmService::MODE_USER_OWN);

		$response = $this->controller->models($this->request('/account/llm/models', ['connection_id' => '0', 'api_type' => 'automatic1111', 'base_url' => 'http://169.254.169.254', 'api_key' => '']), new Response());

		static::assertSame(['success' => false, 'error_message' => $this->text('llm_blocked_address')], json_decode((string) $response->getBody(), true));
	}

	public function testDeleteRemovesTheProviderOfTheUserOnly(): void
	{
		$own     = $this->addOwnProvider(2);
		$foreign = $this->addOwnProvider(3);

		$this->assertBackToTab($this->controller->deleteConnection($this->request('/account/llm/connection/delete', ['connection_id' => (string) $foreign]), new Response()));
		$this->controller->deleteConnection($this->request('/account/llm/connection/delete', ['connection_id' => (string) $own]), new Response());

		static::assertSame([$foreign], array_map('intval', $this->connection->fetchFirstColumn('SELECT connection_id FROM user_llm_connections')));
	}
}
