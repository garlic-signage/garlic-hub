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
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The page of the LLM of the company /users/llm: only the module admin of users manages it, for his company.
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
	private function store(array $post): ResponseInterface
	{
		return $this->controller->store(new ServerRequestFactory()->createServerRequest('POST', '/users/llm')->withParsedBody($post), new Response());
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

	public function testTheAdminSetsUpTheLlmOfHisCompanyAndItsRelease(): void
	{
		$this->actingAs(1);

		$response = $this->store(['api_type' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'model' => 'gpt-5', 'api_key' => 'sk-1', 'released_for' => 'admins']);

		static::assertSame('/users/llm', $response->getHeaderLine('Location'));
		static::assertSame(['1', 'gpt-5', 'admins'], array_map('strval', (array) $this->connection->fetchNumeric('SELECT company_id, model, released_for FROM company_llm')));
		$page = $this->controller->show(new ServerRequestFactory()->createServerRequest('GET', '/users/llm'), new Response());
		static::assertInstanceOf(PageResponse::class, $page);
		$release = $page->getTemplateData()['release'];
		static::assertSame(['admins'], array_column(array_filter($release['releases'], static fn(array $option): bool => $option['is_selected']), 'VALUE'));
	}

	public function testAWrongReleaseIsNotStored(): void
	{
		$this->actingAs(1);

		$this->store(['api_type' => 'openai', 'base_url' => 'https://api.openai.com/v1', 'model' => 'gpt-5', 'released_for' => 'everybody']);

		static::assertSame([$this->container->get(Translator::class)->translate('llm_invalid_release', 'main')], $this->flash('error'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM company_llm'));
	}

	public function testAUserWithoutTheRightChangesNothing(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("INSERT INTO company_llm (company_id, base_url, model) VALUES (1, 'http://localhost:11434/v1', 'a')");

		$response = $this->store(['api_type' => 'openai', 'base_url' => 'http://evil.test/v1', 'model' => 'x', 'released_for' => 'all']);
		$deleted  = $this->controller->delete(new ServerRequestFactory()->createServerRequest('POST', '/users/llm/delete'), new Response());

		static::assertSame(['/', '/'], [$response->getHeaderLine('Location'), $deleted->getHeaderLine('Location')]);
		static::assertSame('http://localhost:11434/v1', $this->connection->fetchOne('SELECT base_url FROM company_llm'));
	}
}
