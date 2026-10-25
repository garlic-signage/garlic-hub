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

namespace Tests\Integration\Modules\Player\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\SimpleApiExecutor;
use App\Framework\Validation\BaseValidator;
use App\Modules\Player\Controller\PlayerPlaylistController;
use App\Modules\Player\Helper\PlayerPlaylist\Orchestrator;
use App\Modules\Player\Helper\PlayerPlaylist\ResponseBuilder;
use App\Modules\Player\Repositories\PlayerTokenRepository;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerService;
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

/**
 * The playlist of a player: replace it (/async/player/playlist) and push it to a player in the network. The
 * players in the network are prepared answers of the web.
 */
#[Group('acl')]
#[Group('auth')]
class PlayerPlaylistControllerTest extends PlayerControllerTestCase
{
	private const string ENDPOINT = 'http://10.0.0.7:8080/v2';

	/** @var list<RequestInterface> */
	private array $sent = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->sent = [];
	}

	/**
	 * @param list<Response|\Throwable> $answers
	 */
	private function controller(array $answers = []): PlayerPlaylistController
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
		$rest = new PlayerRestAPIService(
			new SimpleApiExecutor(new Client(['handler' => $stack])),
			new PlayerTokenService(new PlayerTokenRepository($this->connection), $this->container->get(Crypt::class))
		);

		return new PlayerPlaylistController(new Orchestrator(
			new ResponseBuilder($this->container->get(JsonResponseHandler::class), $this->container->get(Translator::class)),
			$this->container->get(BaseValidator::class),
			$this->container->get(PlayerService::class),
			$rest
		));
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function replace(array $body): \Psr\Http\Message\ResponseInterface
	{
		return $this->controller()->replacePlaylist($this->request('PATCH', '/async/player/playlist', $body), $this->newResponse());
	}

	public function testAMasterPlaylistIsSetAtAPlayer(): void
	{
		$this->actingAs(1);

		$response = $this->replace($this->withToken(['player_id' => '3', 'playlist_id' => '1']));

		static::assertSame(['success' => true, 'playlist_name' => 'Annas Lobby'], $this->json($response));
		static::assertSame(1, (int) $this->playerField(3, 'playlist_id'));
	}

	public function testTheIdZeroTakesThePlaylistOff(): void
	{
		$this->setPlayer(3, 'playlist_id = 1');
		$this->actingAs(1);

		$response = $this->replace($this->withToken(['player_id' => 3, 'playlist_id' => 0]));

		static::assertSame(['success' => true, 'playlist_name' => ''], $this->json($response));
		static::assertSame(0, (int) $this->playerField(3, 'playlist_id'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function noPlaylistIds(): array
	{
		return [
			'nothing'  => [[]],
			'a list'   => [['playlist_id' => ['1']]],
			'text'     => [['playlist_id' => 'all']],
			'negative' => [['playlist_id' => '-1']],
			'null'     => [['playlist_id' => null]],
		];
	}

	/**
	 * Only the id 0 takes the playlist off. Nothing, or a value which is no number, changes nothing.
	 *
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('noPlaylistIds')]
	public function testAValueWhichIsNoPlaylistIdChangesNothing(array $body): void
	{
		$this->setPlayer(3, 'playlist_id = 3');
		$this->actingAs(1);

		$this->assertError($this->replace($this->withToken(['player_id' => '3'] + $body)), $this->text('invalid_playlist_id'));
		static::assertSame(3, (int) $this->playerField(3, 'playlist_id'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function noPlayerIds(): array
	{
		return ['nothing' => [[]], 'zero' => [['player_id' => '0']], 'a list' => [['player_id' => ['3']]], 'text' => [['player_id' => 'bernd']], 'negative' => [['player_id' => '-3']]];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('noPlayerIds')]
	public function testAValueWhichIsNoPlayerIdChangesNoPlayer(array $body): void
	{
		$this->actingAs(1);
		$before = $this->connection->fetchAllAssociative('SELECT player_id, playlist_id FROM player ORDER BY player_id');

		$this->assertError($this->replace($this->withToken(['playlist_id' => '1'] + $body)), $this->text('invalid_player_id'));
		static::assertSame($before, $this->connection->fetchAllAssociative('SELECT player_id, playlist_id FROM player ORDER BY player_id'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongTokens(): array
	{
		return ['missing' => [[]], 'forged' => [['csrf_token' => 'forged']], 'empty' => [['csrf_token' => '']], 'a list' => [['csrf_token' => ['x']]]];
	}

	/**
	 * @param array<string,mixed> $token
	 */
	#[DataProvider('wrongTokens')]
	public function testWithoutTheTokenOfThePageNothingIsDone(array $token): void
	{
		$this->actingAs(1);
		$this->csrf();
		$body = $token + ['player_id' => '3', 'playlist_id' => '1'];

		$replace = $this->controller()->replacePlaylist($this->request('PATCH', '/x', $body), $this->newResponse());
		$push    = $this->controller()->pushPlaylist($this->request('POST', '/x', $body), $this->newResponse());

		foreach ([$replace, $push] as $response)
		{
			static::assertSame(400, $response->getStatusCode());
			static::assertFalse($this->json($response)['success']);
		}
		static::assertSame(0, (int) $this->playerField(3, 'playlist_id'));
		static::assertSame([], $this->sent);
	}

	public function testARequestWithoutABodyDoesNothing(): void
	{
		$this->actingAs(1);
		$request = new \Slim\Psr7\Factory\ServerRequestFactory()->createServerRequest('PATCH', '/x');

		static::assertSame(400, $this->controller()->replacePlaylist($request, $this->newResponse())->getStatusCode());
		static::assertSame(400, $this->controller()->pushPlaylist($request, $this->newResponse())->getStatusCode());
	}

	public function testAnInternalPlaylistIsNoMasterPlaylist(): void
	{
		$this->actingAs(1);

		try
		{
			$this->replace($this->withToken(['player_id' => '3', 'playlist_id' => '2']));
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('playlist_not_master', $e->getTranslationKey());
		}
		static::assertSame(0, (int) $this->playerField(3, 'playlist_id'));
	}

	public function testAPlayerOfAnotherUserGetsNoPlaylist(): void
	{
		$this->actingAs(2);

		try
		{
			$this->replace($this->withToken(['player_id' => '3', 'playlist_id' => '1']));
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(0, (int) $this->playerField(3, 'playlist_id'));
		}
	}

	// ---- push

	private function intranetPlayer(): void
	{
		$this->setPlayer(3, 'is_intranet = 1, playlist_id = 1, api_endpoint = ?', self::ENDPOINT);
	}

	private function json200(string $body): Response
	{
		return new Response(200, ['Content-Type' => 'application/json'], $body);
	}

	public function testThePlaylistIsPushedToAPlayerInTheNetwork(): void
	{
		$this->intranetPlayer();
		$this->actingAs(1);
		$controller = $this->controller([
			$this->json200('{"access_token":"tok","expires_in":"2999-01-01 00:00:00","token_type":"Bearer"}'),
			$this->json200('{"uri":"http://10.0.0.7/index.smil"}'),
		]);

		$response = $controller->pushPlaylist($this->request('POST', '/async/player/push', $this->withToken(['player_id' => '3'])), $this->newResponse());

		static::assertSame(['success' => true, 'message' => 'Playlist pushed successfully to Bernds Player.'], $this->json($response));
		static::assertSame(self::ENDPOINT . '/oauth2/token', (string) $this->sent[0]->getUri());
		static::assertStringStartsWith(self::ENDPOINT . '/app/switch?access_token=tok', (string) $this->sent[1]->getUri());
	}

	public function testAPlayerWhichIsNotInTheNetworkIsNotReachable(): void
	{
		$this->setPlayer(3, 'playlist_id = 1');
		$this->actingAs(1);

		$response = $this->controller()->pushPlaylist($this->request('POST', '/x', $this->withToken(['player_id' => '3'])), $this->newResponse());

		$this->assertError($response, $this->text('player_not_reachable'));
		static::assertSame([], $this->sent);
	}

	public function testAPlayerWithoutAPlaylistGetsNothingPushed(): void
	{
		$this->setPlayer(3, 'is_intranet = 1, api_endpoint = ?', self::ENDPOINT);
		$this->actingAs(1);

		$response = $this->controller()->pushPlaylist($this->request('POST', '/x', $this->withToken(['player_id' => '3'])), $this->newResponse());

		$this->assertError($response, $this->text('no_playlist_assigned'));
		static::assertSame([], $this->sent);
	}

	public function testAPlayerOfAnotherUserGetsNothingPushed(): void
	{
		$this->intranetPlayer();
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->controller()->pushPlaylist($this->request('POST', '/x', $this->withToken(['player_id' => '3'])), $this->newResponse());
		}
		finally
		{
			static::assertSame([], $this->sent);
		}
	}

	public function testAPlayerWhichDoesNotAnswerIsAMessageForTheUser(): void
	{
		$this->intranetPlayer();
		$this->actingAs(1);
		$controller = $this->controller([new ConnectException('timeout', new Request('POST', self::ENDPOINT))]);

		try
		{
			$controller->pushPlaylist($this->request('POST', '/x', $this->withToken(['player_id' => '3'])), $this->newResponse());
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('player_not_reachable', $e->getTranslationKey());
		}
	}
}
