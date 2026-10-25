<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Modules\Player\Services;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Security\Crypt;
use App\Framework\SimpleApiExecutor;
use App\Modules\Player\Repositories\PlayerTokenRepository;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerTokenService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The calls to the REST API of the players (IAdea). The players are prepared answers of the web, the tokens
 * are kept in the real database, encrypted.
 */
#[Group('auth')]
class PlayerRestApiTest extends ContainerTestCase
{
	private const string BASE = 'http://player.example:8080/api';

	private PlayerTokenService $tokenService;
	/** @var list<RequestInterface> */
	private array $sent = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/players.sql');
		$this->tokenService = new PlayerTokenService(new PlayerTokenRepository($this->connection), $this->container->get(Crypt::class));
		$this->sent         = [];
	}

	/**
	 * @param list<Response|\Throwable> $answers
	 */
	private function service(array $answers = []): PlayerRestAPIService
	{
		$stack = HandlerStack::create(new MockHandler($answers));
		$stack->push(function (callable $next)
		{
			return function (RequestInterface $request, array $options) use ($next)
			{
				$this->sent[] = $request;

				return $next($request, $options);
			};
		});

		return new PlayerRestAPIService(new SimpleApiExecutor(new Client(['handler' => $stack])), $this->tokenService);
	}

	private function json(string $body): Response
	{
		return new Response(200, ['Content-Type' => 'application/json'], $body);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function bodyOf(int $index): array
	{
		/** @var array<string,mixed> $body */
		$body = json_decode((string) $this->sent[$index]->getBody(), true);

		return $body;
	}

	private function giveToken(int $playerId = 1, string $token = 'a+b/c=d&e'): void
	{
		$this->tokenService->storeToken($playerId, $token, '2999-12-31 23:59:59');
	}

	public function testAPlayerWhichHasAValidTokenIsNotAskedAgain(): void
	{
		$this->giveToken();

		$this->service()->authenticate(self::BASE, 'admin', 'secret', 1);

		static::assertSame([], $this->sent);
	}

	public function testThePlayerGetsTheLoginAndTheTokenIsKept(): void
	{
		$service = $this->service([$this->json('{"access_token":"tok","expires_in":"2999-01-01 00:00:00","token_type":"Bearer"}')]);

		$service->authenticate(self::BASE, 'admin', 'secret', 1);

		static::assertSame('POST', $this->sent[0]->getMethod());
		static::assertSame(self::BASE . '/oauth2/token', (string) $this->sent[0]->getUri());
		static::assertSame(['grant_type' => 'password', 'username' => 'admin', 'password' => 'secret'], $this->bodyOf(0));
		static::assertSame(['access_token' => 'tok', 'token_type' => 'Bearer', 'expires_at' => '2999-01-01 00:00:00'], $this->tokenService->getToken(1));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function incompleteLogins(): array
	{
		return [
			'no token'       => ['{"expires_in":"2999-01-01 00:00:00","token_type":"Bearer"}'],
			'no expiry'      => ['{"access_token":"tok","token_type":"Bearer"}'],
			'no type'        => ['{"access_token":"tok","expires_in":"2999-01-01 00:00:00"}'],
			'not JSON'       => ['Service unavailable'],
			'empty'          => [''],
		];
	}

	#[DataProvider('incompleteLogins')]
	public function testAnIncompleteAnswerStoresNoToken(string $body): void
	{
		try
		{
			$this->service([$this->json($body)])->authenticate(self::BASE, 'admin', 'secret', 1);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('player_request_failed', $e->getTranslationKey());
			static::assertSame(['player_id' => 1, 'reason' => 'Invalid authentication response'], $e->getReplacements());
		}

		static::assertSame([], $this->tokenService->getToken(1));
	}

	/**
	 * @return array<string,array{Response|\Throwable,string}>
	 */
	public static function failingPlayers(): array
	{
		return [
			'not reachable' => [new ConnectException('Connection refused', new Request('POST', 'http://player.example')), 'player_not_reachable'],
			'wrong login'   => [new Response(401, [], '{"error":"invalid_grant"}'), 'player_request_failed'],
			'server error'  => [new Response(500), 'player_request_failed'],
		];
	}

	#[DataProvider('failingPlayers')]
	public function testAPlayerWhichDoesNotAnswerIsAMessageForTheUser(Response|\Throwable $answer, string $key): void
	{
		try
		{
			$this->service([$answer])->authenticate(self::BASE, 'admin', 'secret', 1);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame($key, $e->getTranslationKey());
			static::assertSame(1, $e->getReplacements()['player_id']);
		}
	}

	public function testAFileIsUploadedWithItsContentAndSize(): void
	{
		$this->giveToken();
		$file = sys_get_temp_dir() . '/garlic-hub-index-' . bin2hex(random_bytes(3)) . '.smil';
		file_put_contents($file, '<smil>content</smil>');

		try
		{
			$this->service([$this->json('{"completed":true}')])->uploadFile(self::BASE, 1, $file, 'index.smil');
		}
		finally
		{
			unlink($file);
		}

		static::assertSame(self::BASE . '/files/new?access_token=' . rawurlencode('a+b/c=d&e'), (string) $this->sent[0]->getUri());
		$body = $this->bodyOf(0);
		static::assertSame($file, $body['downloadPath']);
		static::assertSame(20, $body['fileSize']);
		static::assertStringContainsString("\r\n\r\n<smil>content</smil>\r\n", $body['data']);
	}

	public function testAFileWhichIsNotThereIsAnError(): void
	{
		$this->giveToken();

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('does not exist');

		@$this->service([$this->json('{"completed":true}')])->uploadFile(self::BASE, 1, '/tmp/garlic-hub-nothing.smil', 'index.smil');
	}

	public function testAnUploadWhichIsNotCompletedIsAMessageForTheUser(): void
	{
		$this->giveToken();
		$file = sys_get_temp_dir() . '/garlic-hub-index-' . bin2hex(random_bytes(3));
		file_put_contents($file, 'x');

		try
		{
			$this->service([$this->json('{"completed":false}')])->uploadFile(self::BASE, 1, $file, 'index.smil');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('Upload file failed', $e->getReplacements()['reason']);
		}
		finally
		{
			unlink($file);
		}
	}

	/**
	 * @return array<string,array{string,string,array<string,string>,string}> the method, the path, the body, the answer which means success
	 */
	public static function commands(): array
	{
		return [
			'play once'          => ['startPlaybackOnce', '/app/exec', ['uri' => 'http://hub.example/smil-index'], '{"uri":"http://hub.example/smil-index"}'],
			'default content'    => ['setDefaultContentUrl', '/app/start', ['uri' => 'http://hub.example/smil-index'], '{"uri":"http://hub.example/smil-index"}'],
		];
	}

	/**
	 * @param array<string,string> $body
	 */
	#[DataProvider('commands')]
	public function testACommandWithAnAddressIsConfirmedByThePlayer(string $method, string $path, array $body, string $answer): void
	{
		$this->giveToken();
		$service = $this->service([$this->json($answer)]);

		$service->$method(self::BASE, 1, 'http://hub.example/smil-index');

		static::assertSame(self::BASE . $path . '?access_token=' . rawurlencode('a+b/c=d&e'), (string) $this->sent[0]->getUri());
		static::assertSame($body, $this->bodyOf(0));
	}

	/**
	 * @param array<string,string> $body
	 */
	#[DataProvider('commands')]
	public function testACommandWhichThePlayerDoesNotConfirmIsAMessageForTheUser(string $method, string $path, array $body, string $answer): void
	{
		$this->giveToken();

		try
		{
			$this->service([$this->json('{"uri":"http://other.example/"}')])->$method(self::BASE, 1, 'http://hub.example/smil-index');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('player_request_failed', $e->getTranslationKey());
		}
	}

	public function testSwitchingToTheDefaultContentNeedsAnAddressInTheAnswer(): void
	{
		$this->giveToken();
		$this->service([$this->json('{"uri":"http://hub.example/smil-index"}')])->switchToDefaultContentUrl(self::BASE, 1);
		static::assertSame(['mode' => 'start'], $this->bodyOf(0));
		static::assertStringContainsString('/app/switch?', (string) $this->sent[0]->getUri());

		foreach (['{"uri":""}', '{}'] as $answer)
		{
			try
			{
				$this->service([$this->json($answer)])->switchToDefaultContentUrl(self::BASE, 1);
				static::fail('Expected a TranslatableModuleException');
			}
			catch (TranslatableModuleException $e)
			{
				static::assertSame('Switch play default content uri failed', $e->getReplacements()['reason']);
			}
		}
	}

	public function testWithoutATokenNoCommandIsSent(): void
	{
		$service = $this->service([$this->json('{}')]);

		foreach ([
			fn() => $service->startPlaybackOnce(self::BASE, 1, 'http://x'),
			fn() => $service->setDefaultContentUrl(self::BASE, 1, 'http://x'),
			fn() => $service->switchToDefaultContentUrl(self::BASE, 1),
		] as $call)
		{
			try
			{
				$call();
				static::fail('Expected a TranslatableModuleException');
			}
			catch (TranslatableModuleException $e)
			{
				static::assertSame('player_not_authenticated', $e->getTranslationKey());
			}
		}

		static::assertSame([], $this->sent);
	}

	public function testThePlayerIsNotReachableForACommand(): void
	{
		$this->giveToken();
		$service = $this->service([new ConnectException('timeout', new Request('POST', self::BASE))]);

		try
		{
			$service->startPlaybackOnce(self::BASE, 1, 'http://x');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('player_not_reachable', $e->getTranslationKey());
			static::assertInstanceOf(ConnectException::class, $e->getPrevious());
		}
	}

	public function testATokenOfAnotherPlayerIsNotUsed(): void
	{
		$this->giveToken(2, 'token-of-2');

		try
		{
			$this->service()->startPlaybackOnce(self::BASE, 1, 'http://x');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('player_not_authenticated', $e->getTranslationKey());
		}
	}
}
