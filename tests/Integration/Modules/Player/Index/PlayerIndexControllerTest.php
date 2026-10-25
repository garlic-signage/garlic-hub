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

namespace Tests\Integration\Modules\Player\Index;

use App\Framework\Validation\Sanitizer;
use App\Modules\Player\Controller\PlayerIndexController;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\Helper\Index\FileUtils;
use App\Modules\Player\Helper\Index\IndexResponseHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The public endpoint /smil-index as a player calls it: the agent, the name of the server and the headers decide.
 */
#[Group('acl')]
#[Group('io')]
class PlayerIndexControllerTest extends PlayerIndexTestCase
{
	/**
	 * @param array<string,string> $server
	 * @param array<string,mixed> $query
	 */
	private function call(array $server, array $query = ['owner_id' => '2'], string $method = 'GET'): ResponseInterface
	{
		$controller = new PlayerIndexController($this->service(), new IndexResponseHandler(new FileUtils()), new Sanitizer());
		$request    = new ServerRequestFactory()->createServerRequest($method, '/smil-index', $server + ['SERVER_NAME' => 'hub.example.com', 'REQUEST_METHOD' => $method])->withQueryParams($query);

		return $controller->index($request, new Response());
	}

	public function testAKnownPlayerGetsItsIndexWithTheHeadersOfTheFile(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 1);

		$response = $this->call(['HTTP_USER_AGENT' => self::AGENT]);

		static::assertSame(200, $response->getStatusCode());
		static::assertStringContainsString('Eingang - Annas Lobby', (string) $response->getBody());
		static::assertSame('application/smil+xml', $response->getHeaderLine('Content-Type'));
		static::assertSame('User-Agent, X-Signage-Agent', $response->getHeaderLine('Vary'));
		static::assertSame('attachment; filename="1.smil"', $response->getHeaderLine('Content-Disposition'));
	}

	public function testAPlayerWhichHasTheCurrentIndexGetsNotModified(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 1);
		$first = $this->call(['HTTP_USER_AGENT' => self::AGENT]);

		$second = $this->call(['HTTP_USER_AGENT' => self::AGENT, 'HTTP_IF_NONE_MATCH' => $first->getHeaderLine('etag')]);

		static::assertSame(304, $second->getStatusCode());
		static::assertSame('', (string) $second->getBody());
	}

	public function testHeadHasNoBody(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 1);

		$response = $this->call(['HTTP_USER_AGENT' => self::AGENT], ['owner_id' => '2'], 'HEAD');

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('', (string) $response->getBody());
		static::assertNotSame('', $response->getHeaderLine('etag'));
	}

	/**
	 * A player of JavaScript can not set the user agent: the header X-Signage-Agent is taken first.
	 */
	public function testTheSignageAgentIsTakenBeforeTheUserAgent(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 1);

		$response = $this->call(['HTTP_X_SIGNAGE_AGENT' => self::AGENT, 'HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox/120.0']);

		static::assertStringContainsString('Eingang - Annas Lobby', (string) $response->getBody());
	}

	/**
	 * @return array<string,array{array<string,string>}>
	 */
	public static function strangers(): array
	{
		return [
			'a browser'          => [['HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox/120.0']],
			'no agent at all'    => [[]],
			'an empty agent'     => [['HTTP_USER_AGENT' => '']],
			'a wrong uuid'       => [['HTTP_USER_AGENT' => 'GAPI/1.0 (UUID:../../etc/passwd; NAME:x) garlic/v1 (MODEL:Garlic)']],
		];
	}

	/**
	 * Whoever is not a player gets the file for the forbidden, and nothing is registered.
	 *
	 * @param array<string,string> $server
	 */
	#[DataProvider('strangers')]
	public function testAStrangerGetsTheForbiddenFile(array $server): void
	{
		$response = $this->call($server);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame((string) file_get_contents($this->directory . '/public/smil/defaults/forbidden.smil'), (string) $response->getBody());
		static::assertSame(0, $this->countPlayers());
	}

	public function testAnErrorOfTheServiceIsAnEmptyAnswerOfThePlayer(): void
	{
		$this->givePlayer(99);

		$response = $this->call(['HTTP_USER_AGENT' => self::AGENT]);

		static::assertSame(404, $response->getStatusCode());
		static::assertSame('application/smil+xml', $response->getHeaderLine('Content-Type'));
		static::assertSame('', (string) $response->getBody());
	}

	/**
	 * @return array<string,array{string,bool}> the name of the server and whether this is the local machine
	 */
	public static function serverNames(): array
	{
		return [
			'localhost'                          => ['localhost', true],
			'upper case'                         => ['LOCALHOST', true],
			'ddev'                               => ['garlic-hub.ddev.site', true],
			'a host which has localhost in it'   => ['localhost.evil.example', false],
			'a host which ends with localhost'   => ['evil-localhost', false],
			'a host which has ddev in it'        => ['ddev.evil.example', false],
			'a name which only ends like ddev'   => ['garlic-hub.ddev.site.evil.example', false],
			'a normal host'                      => ['hub.example.com', false],
			'empty'                              => ['', false],
		];
	}

	/**
	 * The name of the server can come from the Host header: a name which only contains "localhost" is no local
	 * machine. The local player is the one with the id 1, it is released at once and has the API of the machine.
	 */
	#[DataProvider('serverNames')]
	public function testOnlyTheLocalMachineHasALocalPlayer(string $serverName, bool $isLocal): void
	{
		$this->call(['HTTP_USER_AGENT' => self::AGENT, 'SERVER_NAME' => $serverName]);

		$row = $this->playerRow();
		static::assertNotSame([], $row);
		static::assertSame($isLocal, (int) $row['player_id'] === 1 && $row['api_endpoint'] !== '');
	}

	/**
	 * The local player is the one with the id 1. A player of another host is registered with the next id and
	 * does not take its place.
	 */
	public function testAPlayerOfAnotherHostIsNoLocalPlayer(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 0, '15920d5d-7e68-4a61-a145-15b58b6d2090', 5);

		$this->call(['HTTP_USER_AGENT' => self::AGENT, 'SERVER_NAME' => 'localhost.evil.example']);

		static::assertSame(6, (int) $this->playerRow()['player_id']);
		static::assertSame('', $this->playerRow()['api_endpoint']);
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player WHERE player_id = 1'));
	}

	/**
	 * @return array<string,array{mixed,int}> the owner in the address and the owner of the new player
	 */
	public static function owners(): array
	{
		return [
			'a number'        => ['3', 3],
			'a number as int' => [3, 3],
			'text'            => ['abc', 0],
			'a list'          => [['3'], 0],
			'missing'         => [null, 0],
		];
	}

	#[DataProvider('owners')]
	public function testTheOwnerOfANewPlayerIsTheNumberInTheAddress(mixed $owner, int $expected): void
	{
		$this->call(['HTTP_USER_AGENT' => self::AGENT], $owner === null ? [] : ['owner_id' => $owner]);

		static::assertSame($expected, (int) $this->playerRow()['UID']);
	}
}
