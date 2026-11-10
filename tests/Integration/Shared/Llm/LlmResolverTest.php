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
use App\Framework\Llm\LlmMessage;
use App\Framework\Llm\LlmRequest;
use App\Shared\Llm\CompanyLlmService;
use App\Shared\Llm\LlmResolver;
use App\Shared\Llm\LlmTask;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Which AI a user works with for a task, as his company decided: the AI of the company for all or only for the
 * admins, his own one, or none. The test sends a request and looks where it goes. Users: 1 admin (module admin of
 * all modules), 2 anna and 3 bernd without rights, all of the company 1.
 */
#[Group('acl')]
class LlmResolverTest extends ContainerTestCase
{
	private const string OWN     = 'http://own.test/v1';
	private const string COMPANY = 'http://company.test/v1';
	private const string TEXT    = LlmTask::TEXT;
	private const string IMAGES  = LlmTask::IMAGE_TAGGING;

	private LlmResolver $resolver;
	private MockHandler $endpoint;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->endpoint = new MockHandler();
		$container      = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)]), static fn(string $host): array => []));
		$this->resolver = $container->get(LlmResolver::class);
	}

	private function ownLlm(int $UID, string $task = self::TEXT): void
	{
		$this->connection->executeStatement('INSERT INTO user_llm_connections (UID, name, base_url) VALUES (?, ?, ?)', [$UID, 'own', self::OWN]);
		$this->connection->executeStatement('INSERT INTO user_llm_tasks (UID, task, connection_id, model) VALUES (?, ?, ?, ?)', [$UID, $task, $this->connection->lastInsertId(), 'own']);
	}

	private function companyDecides(string $mode, string $task = self::TEXT, int $companyId = 1): void
	{
		$connectionId = null;
		if (CompanyLlmService::isCompanyMode($mode))
		{
			$this->connection->executeStatement('INSERT INTO company_llm_connections (company_id, name, base_url) VALUES (?, ?, ?)', [$companyId, 'company', self::COMPANY]);
			$connectionId = $this->connection->lastInsertId();
		}
		$this->connection->executeStatement('INSERT INTO company_llm_tasks (company_id, task, mode, connection_id, model) VALUES (?, ?, ?, ?, ?)', [$companyId, $task, $mode, $connectionId, $connectionId === null ? '' : 'company']);
	}

	/**
	 * @return string the address the request of the user went to, '' without an AI
	 */
	private function usedBy(int $UID, string $task = self::TEXT): string
	{
		if (!$this->resolver->isAvailable($UID, $task))
			return '';

		$this->endpoint->append(new Response(200, ['Content-Type' => 'application/json'], '{"id":"1","object":"chat.completion","created":1,"model":"m","choices":[{"index":0,"message":{"role":"assistant","content":"ok"},"finish_reason":"stop"}]}'));
		$this->resolver->forUser($UID, $task)->complete(new LlmRequest('', [LlmMessage::user('Hi')]));
		$uri = $this->endpoint->getLastRequest()?->getUri();

		return $uri === null ? '' : $uri->getScheme() . '://' . $uri->getHost() . '/v1';
	}

	/**
	 * The admin keeps control: as long as he did not decide, the own AI of a user is not used.
	 */
	public function testWithoutADecisionOfTheCompanyATaskIsOff(): void
	{
		$this->ownLlm(2);

		static::assertSame('', $this->usedBy(2));
		static::assertSame(array_fill_keys(LlmTask::TASKS, LlmResolver::ACCESS_OFF), $this->resolver->findAccess(2));
	}

	public function testWhenTheCompanyAllowsItEveryUserWorksWithHisOwn(): void
	{
		$this->ownLlm(2);
		$this->companyDecides(CompanyLlmService::MODE_USER_OWN);

		static::assertSame([self::OWN, ''], [$this->usedBy(2), $this->usedBy(3)]);
	}

	public function testTheAiOfTheCompanyForAllReplacesTheOwnOnes(): void
	{
		$this->ownLlm(2);
		$this->companyDecides(CompanyLlmService::MODE_COMPANY_ALL);

		static::assertSame([self::COMPANY, self::COMPANY], [$this->usedBy(2), $this->usedBy(3)]);
		static::assertSame(LlmResolver::ACCESS_COMPANY, $this->resolver->findAccess(2)[self::TEXT]);
	}

	/**
	 * The own AI of a user does not come back: the company decided who works with an AI.
	 */
	public function testTheAiOfTheCompanyForAdminsLeavesTheOtherUsersWithout(): void
	{
		$this->ownLlm(2);
		$this->companyDecides(CompanyLlmService::MODE_COMPANY_ADMINS);

		static::assertSame([self::COMPANY, '', ''], [$this->usedBy(1), $this->usedBy(2), $this->usedBy(3)]);
		$this->expectException(LlmException::class);
		$this->resolver->forUser(2, self::TEXT);
	}

	public function testASubAdminOfAnyModuleIsAnAdmin(): void
	{
		$this->companyDecides(CompanyLlmService::MODE_COMPANY_ADMINS);
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 4, 'templates'), (3, 2, 'templates')");

		// 4 is the sub admin of templates, 2 only an editor
		static::assertSame([self::COMPANY, ''], [$this->usedBy(2), $this->usedBy(3)]);
	}

	public function testEveryTaskHasItsOwnDecision(): void
	{
		$this->ownLlm(2, self::TEXT);
		$this->ownLlm(2, self::IMAGES);
		$this->companyDecides(CompanyLlmService::MODE_COMPANY_ALL, self::TEXT);
		$this->companyDecides(CompanyLlmService::MODE_USER_OWN, self::IMAGES);

		static::assertSame([self::COMPANY, self::OWN, ''], [$this->usedBy(2, self::TEXT), $this->usedBy(2, self::IMAGES), $this->usedBy(2, LlmTask::IMAGE_GENERATION)]);
	}

	public function testAUserWorksWithTheAiOfHisCompany(): void
	{
		$this->connection->executeStatement('UPDATE user_main SET company_id = 2 WHERE UID = 2');
		$this->companyDecides(CompanyLlmService::MODE_COMPANY_ALL, self::TEXT, 2);

		static::assertSame(self::COMPANY, $this->usedBy(2));
		static::assertSame('', $this->usedBy(3), 'a user of company 1');
	}

	public function testTheDecisionOfAnotherCompanyDoesNotCount(): void
	{
		$this->ownLlm(2);
		$this->companyDecides(CompanyLlmService::MODE_USER_OWN, self::TEXT, 2);

		static::assertSame('', $this->usedBy(2));
	}

	/**
	 * The task keeps its mode without the provider, but has no AI any more.
	 */
	public function testADeletedProviderOfTheCompanyLeavesTheTaskWithout(): void
	{
		$this->companyDecides(CompanyLlmService::MODE_COMPANY_ALL);

		$this->container->get(CompanyLlmService::class)->deleteConnection(1, (int) $this->connection->fetchOne('SELECT connection_id FROM company_llm_connections'));

		static::assertSame('', $this->usedBy(2));
		static::assertSame([CompanyLlmService::MODE_COMPANY_ALL, null], $this->connection->fetchNumeric('SELECT mode, connection_id FROM company_llm_tasks'));
	}
}
