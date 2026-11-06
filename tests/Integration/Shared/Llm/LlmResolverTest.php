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
use App\Shared\Llm\LlmResolver;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Which LLM a user works with: the one of his company, as it is released, otherwise his own one.
 * The test sends a request and looks where it goes. Users: 1 admin (module admin of all modules), 2 anna and
 * 3 bernd without rights, all of the company 1.
 */
#[Group('acl')]
class LlmResolverTest extends ContainerTestCase
{
	private const string OWN     = 'http://own.test/v1';
	private const string COMPANY = 'http://company.test/v1';

	private LlmResolver $resolver;
	private MockHandler $endpoint;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->endpoint = new MockHandler();
		$container      = $this->container;
		static::assertInstanceOf(Container::class, $container);
		$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($this->endpoint)])));
		$this->resolver = $container->get(LlmResolver::class);
	}

	private function ownLlm(int $UID): void
	{
		$this->connection->executeStatement('INSERT INTO user_llm (UID, base_url, model) VALUES (?, ?, ?)', [$UID, self::OWN, 'own']);
	}

	private function companyLlm(string $releasedFor, int $companyId = 1): void
	{
		$this->connection->executeStatement('INSERT INTO company_llm (company_id, base_url, model, released_for) VALUES (?, ?, ?, ?)', [$companyId, self::COMPANY, 'company', $releasedFor]);
	}

	/**
	 * @return string the address the request of the user went to, '' without an LLM
	 */
	private function usedBy(int $UID): string
	{
		if (!$this->resolver->isAvailable($UID))
			return '';

		$this->endpoint->append(new Response(200, ['Content-Type' => 'application/json'], '{"id":"1","object":"chat.completion","created":1,"model":"m","choices":[{"index":0,"message":{"role":"assistant","content":"ok"},"finish_reason":"stop"}]}'));
		$this->resolver->forUser($UID)->complete(new LlmRequest('', [LlmMessage::user('Hi')]));
		$uri = $this->endpoint->getLastRequest()?->getUri();

		return $uri === null ? '' : $uri->getScheme() . '://' . $uri->getHost() . '/v1';
	}

	public function testWithoutAnLlmOfTheCompanyEveryUserWorksWithHisOwn(): void
	{
		$this->ownLlm(2);

		static::assertSame([self::OWN, ''], [$this->usedBy(2), $this->usedBy(3)]);
		static::assertFalse($this->resolver->hasCompanyLlm(2));
	}

	public function testTheLlmOfTheCompanyForAllReplacesTheOwnOnes(): void
	{
		$this->ownLlm(2);
		$this->companyLlm('all');

		static::assertSame([self::COMPANY, self::COMPANY], [$this->usedBy(2), $this->usedBy(3)]);
		static::assertTrue($this->resolver->hasCompanyLlm(2));
	}

	/**
	 * The own LLM of a user does not come back: the company decided who works with an LLM.
	 */
	public function testTheLlmOfTheCompanyForAdminsLeavesTheOtherUsersWithout(): void
	{
		$this->ownLlm(2);
		$this->companyLlm('admins');

		static::assertSame([self::COMPANY, '', ''], [$this->usedBy(1), $this->usedBy(2), $this->usedBy(3)]);
		$this->expectException(LlmException::class);
		$this->resolver->forUser(2);
	}

	public function testASubAdminOfAnyModuleIsAnAdmin(): void
	{
		$this->companyLlm('admins');
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 4, 'templates'), (3, 2, 'templates')");

		// 4 is the sub admin of templates, 2 only an editor
		static::assertSame([self::COMPANY, ''], [$this->usedBy(2), $this->usedBy(3)]);
	}

	public function testTheLlmOfAnotherCompanyDoesNotCount(): void
	{
		$this->ownLlm(2);
		$this->companyLlm('all', 2);

		static::assertSame(self::OWN, $this->usedBy(2));
	}
}
