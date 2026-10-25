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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Player\Controller\PlayerController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The player endpoints /async/player of the list of players: what the user may do, the data, deleting.
 */
#[Group('acl')]
class PlayerControllerTest extends PlayerControllerTestCase
{
	private PlayerController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(PlayerController::class);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function rights(int|string $playerId): array
	{
		return $this->json($this->controller->determineRights($this->request('GET', '/async/player/rights'), $this->newResponse(), ['player_id' => (string) $playerId]));
	}

	public function testTheAdminMayChangeAndDeleteAPlayerWithALicence(): void
	{
		$this->setPlayer(3, 'status = 2, licence_id = 1, playlist_id = 1, is_intranet = 1');
		$this->actingAs(1);

		static::assertSame(
			['success' => true, 'can_edit' => true, 'can_delete' => true, 'has_playlist' => true, 'playlist_id' => 1, 'is_intranet' => true],
			$this->rights(3)
		);
	}

	public function testAPlayerWithoutAPlaylistAndNotInTheIntranet(): void
	{
		$this->setPlayer(3, 'status = 2, licence_id = 1');
		$this->actingAs(1);

		$rights = $this->rights(3);

		static::assertFalse($rights['has_playlist']);
		static::assertFalse($rights['is_intranet']);
	}

	/**
	 * @return array<string,array{string}> a state of the player in which nothing is allowed
	 */
	public static function playersWithoutRights(): array
	{
		return [
			'not released'        => ['status = 1, licence_id = 1'],
			'not registered'      => ['status = 0, licence_id = 1'],
			'without a licence'   => ['status = 2, licence_id = 0'],
			'neither'             => ['status = 0, licence_id = 0'],
		];
	}

	#[DataProvider('playersWithoutRights')]
	public function testAPlayerWhichIsNotReleasedOrHasNoLicenceCanNotBeChanged(string $state): void
	{
		$this->setPlayer(3, $state);
		$this->actingAs(1);

		static::assertSame(['success' => true, 'can_edit' => false, 'can_delete' => false], $this->rights(3));
	}

	/**
	 * Asking what a user may do is no error: the answer is "nothing".
	 *
	 * @return array<string,array{int,int|string}> the user and the player
	 */
	public static function nothingAllowed(): array
	{
		return [
			'a player of another user' => [2, 3],
			'an unknown player'        => [1, 999],
			'no id'                    => [1, 0],
			'an id which is text'      => [1, 'abc'],
			'a negative id'            => [1, '-4'],
		];
	}

	#[DataProvider('nothingAllowed')]
	public function testAUserWithoutTheRightGetsNothingAllowedAndNoError(int $user, int|string $playerId): void
	{
		$this->setPlayer(3, 'status = 2, licence_id = 1');
		$this->actingAs($user);

		static::assertSame(['success' => true, 'can_edit' => false, 'can_delete' => false], $this->rights($playerId));
	}

	public function testThePlayerIsGivenToWhoMayChangeIt(): void
	{
		$this->actingAs(1);

		$response = $this->controller->fetchPlayer($this->request('GET', '/async/player/3'), $this->newResponse(), ['player_id' => '3']);

		$data = $this->json($response);
		static::assertTrue($data['success']);
		static::assertSame('Bernds Player', $data['player_name']);
		static::assertSame(3, $data['player_id']);
	}

	public function testThePlayerIsNotGivenToAnotherUser(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->controller->fetchPlayer($this->request('GET', '/async/player/3'), $this->newResponse(), ['player_id' => '3']);
	}

	public function testAnUnknownPlayerIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->controller->fetchPlayer($this->request('GET', '/async/player/999'), $this->newResponse(), ['player_id' => '999']);
	}

	public function testAPlayerWithoutAnIdIsAnError(): void
	{
		$this->actingAs(1);

		$this->assertError($this->controller->fetchPlayer($this->request('GET', '/async/player/0'), $this->newResponse(), ['player_id' => '0']), 'No playerId');
	}

	public function testThePlayerIsDeletedByTheAdmin(): void
	{
		$this->actingAs(1);

		$response = $this->controller->delete($this->request('DELETE', '/async/player', $this->withToken(['player_id' => '3'])), $this->newResponse());

		static::assertSame(200, $response->getStatusCode());
		static::assertTrue($this->json($response)['success']);
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player WHERE player_id = 3'));
		static::assertSame(3, $this->countPlayers());
	}

	public function testThePlayerIsDeletedWithNumbersFromJson(): void
	{
		$this->actingAs(1);

		$this->controller->delete($this->request('DELETE', '/async/player', $this->withToken(['player_id' => 3])), $this->newResponse());

		static::assertSame(3, $this->countPlayers());
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongTokens(): array
	{
		return [
			'missing' => [[]],
			'forged'  => [['csrf_token' => 'forged']],
			'empty'   => [['csrf_token' => '']],
			'a list'  => [['csrf_token' => ['x']]],
			'a number' => [['csrf_token' => 5]],
		];
	}

	/**
	 * @param array<string,mixed> $token
	 */
	#[DataProvider('wrongTokens')]
	public function testWithoutTheTokenOfThePageNothingIsDeleted(array $token): void
	{
		$this->actingAs(1);
		$this->csrf();

		$response = $this->controller->delete($this->request('DELETE', '/async/player', $token + ['player_id' => '3']), $this->newResponse());

		$this->assertError($response, 'CSRF token mismatch.');
		static::assertSame(4, $this->countPlayers());
	}

	public function testARequestWithoutABodyDeletesNothing(): void
	{
		$this->actingAs(1);
		$request = new \Slim\Psr7\Factory\ServerRequestFactory()->createServerRequest('DELETE', '/async/player');

		$this->assertError($this->controller->delete($request, $this->newResponse()), 'CSRF token mismatch.');
		static::assertSame(4, $this->countPlayers());
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function noPlayerIds(): array
	{
		return [
			'nothing'  => [[]],
			'zero'     => [['player_id' => '0']],
			'a list'   => [['player_id' => ['3']]],
			'text'     => [['player_id' => 'all']],
			'negative' => [['player_id' => '-3']],
			'with sql' => [['player_id' => '3 OR 1=1']],
			'null'     => [['player_id' => null]],
		];
	}

	/**
	 * The id has to be a number: a list is not taken for the player 1.
	 *
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('noPlayerIds')]
	public function testADeleteWithoutAUsablePlayerIdDeletesNothing(array $body): void
	{
		$this->actingAs(1);

		$this->assertError($this->controller->delete($this->request('DELETE', '/async/player', $this->withToken($body)), $this->newResponse()), 'No playerId');
		static::assertSame(4, $this->countPlayers());
	}

	public function testAPlayerOfAnotherUserIsNotDeleted(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->delete($this->request('DELETE', '/async/player', $this->withToken(['player_id' => '3'])), $this->newResponse());
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(4, $this->countPlayers());
		}
	}

	public function testAnUnknownPlayerIsNotDeleted(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->controller->delete($this->request('DELETE', '/async/player', $this->withToken(['player_id' => '999'])), $this->newResponse());
	}
}
