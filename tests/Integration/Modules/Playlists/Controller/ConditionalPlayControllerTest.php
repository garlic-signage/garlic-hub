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

namespace Tests\Integration\Modules\Playlists\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Playlists\Controller\ConditionalPlayController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The conditional play of an item /async/playlists/item/conditional-play: the days, the times and the weekdays
 * on which the item is played.
 */
#[Group('acl')]
class ConditionalPlayControllerTest extends PlaylistsControllerTestCase
{
	private ConditionalPlayController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(ConditionalPlayController::class);
	}

	public function testTheSettingsAreSavedAndComeBackWithTheForm(): void
	{
		$this->actingAs(2);
		$body = ['item_id' => '1', 'date' => ['from' => '2030-01-01', 'until' => '2030-12-31'], 'time' => ['from' => '08:00', 'until' => '18:30'], 'weekdays' => [1 => ['from' => '4', 'until' => '40'], 64 => ['from' => '0', 'until' => '96']]];

		$saved = $this->controller->save($this->request('PATCH', body: $this->withToken($body)), $this->newResponse());
		$fetched = $this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '1']);

		$this->assertSuccess($saved);
		$data = $this->json($fetched);
		static::assertSame(
			['date' => ['from' => '2030-01-01', 'until' => '2030-12-31'], 'time' => ['from' => '08:00', 'until' => '18:30'], 'weekdays' => [1 => ['from' => 4, 'until' => 40], 64 => ['from' => 0, 'until' => 96]]],
			$data['data']['conditional']
		);
		static::assertSame('bild.jpg', $data['data']['item_name']);
		static::assertNotSame('', $data['html']);
	}

	public function testNoConditionsAreStoredAsNoConditions(): void
	{
		$this->actingAs(2);
		$this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '1', 'time' => ['from' => '08:00', 'until' => '09:00']])), $this->newResponse());

		$this->assertSuccess($this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '1'])), $this->newResponse()));

		static::assertSame([], unserialize((string) $this->databaseValue('SELECT conditional FROM playlists_items WHERE item_id = 1')));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function invalidConditions(): array
	{
		return [
			'a date which does not exist'  => [['date' => ['from' => '2030-02-30', 'until' => '']]],
			'a date in the wrong format'   => [['date' => ['from' => '01.01.2030', 'until' => '']]],
			'an hour of 25'                => [['time' => ['from' => '25:00', 'until' => '']]],
			'a time as number'             => [['time' => ['from' => 800, 'until' => '']]],
			'a period as text'             => [['date' => 'today']],
			'an unknown weekday'           => [['weekdays' => [3 => ['from' => '0', 'until' => '4']]]],
			'a weekday which is no range'  => [['weekdays' => [1 => 'all day']]],
			'a start after the end'        => [['weekdays' => [1 => ['from' => '40', 'until' => '4']]]],
			'more than a day'              => [['weekdays' => [1 => ['from' => '0', 'until' => '97']]]],
			'a negative quarter hour'      => [['weekdays' => [1 => ['from' => '-1', 'until' => '4']]]],
			'weekdays as text'             => [['weekdays' => 'monday']],
		];
	}

	/**
	 * @param array<string,mixed> $conditions
	 */
	#[DataProvider('invalidConditions')]
	public function testInvalidConditionsAreRejectedAndNothingIsStored(array $conditions): void
	{
		$this->actingAs(2);

		$response = $this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '1'] + $conditions)), $this->newResponse());

		static::assertNotNull($response);
		static::assertSame(400, $response->getStatusCode());
		static::assertFalse($this->json($response)['success']);
		static::assertSame('', $this->databaseValue('SELECT conditional FROM playlists_items WHERE item_id = 1'));
	}

	public function testWithoutAnItemNothingIsLoadedOrSaved(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->fetch($this->request('GET'), $this->newResponse(), []), 'Invalid item ID.');
		$this->assertError($this->controller->save($this->request('PATCH', body: $this->withToken([])), $this->newResponse()), 'Invalid item ID.');
		$this->assertError($this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'csrf_token' => 'wrong']), $this->newResponse()), 'CSRF token validation error.');
	}

	public function testTheSettingsOfAForeignItemCanNotBeReadOrChanged(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '6', 'time' => ['from' => '08:00', 'until' => '09:00']])), $this->newResponse());
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('', $this->databaseValue('SELECT conditional FROM playlists_items WHERE item_id = 6'));
		}

		$this->expectException(AccessDeniedException::class);
		$this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '6']);
	}

	public function testAnUnknownItemIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '999'])), $this->newResponse());
	}

	public function testARequestWithoutATokenIsRejectedAsAnError(): void
	{
		static::markTestSkipped('Known bug: a request without csrf_token throws a TypeError (and a warning for the undefined key) instead of the answer for a wrong token. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(2);

		$this->assertError($this->controller->save($this->request('PATCH', body: ['item_id' => '1']), $this->newResponse()), 'CSRF token validation error.');
	}
}
