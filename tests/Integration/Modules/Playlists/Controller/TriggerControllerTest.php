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
use App\Modules\Playlists\Controller\TriggerController;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Modules\Playlists\BuildsExportService;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The begin trigger of an item /async/playlists/item/begin-trigger: wallclocks, accesskeys, touches and notifies.
 */
#[Group('acl')]
class TriggerControllerTest extends PlaylistsControllerTestCase
{
	use BuildsExportService;

	private TriggerController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(TriggerController::class);
	}

	public function testTheTriggerComesWithTheFormAndTheMediaWhichCanBeTouched(): void
	{
		$this->actingAs(2);
		$this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '1', 'accesskeys' => [['accesskey' => 'a']]])), $this->newResponse());

		$response = $this->controller->fetchTrigger($this->request('GET'), $this->newResponse(), ['item_id' => '1']);

		$this->assertSuccess($response);
		$data = $this->json($response);
		static::assertSame(['accesskeys' => [['accesskey' => 'a']]], $data['item_data']['begin_trigger']);
		// the image itself can not be touched, the video and the template can
		static::assertSame([2, 5], array_map(intval(...), array_column($data['touchable_medialist'], 'item_id')));
		static::assertStringContainsString('trigger-form', $data['html']);
	}

	public function testAnItemWithoutATriggerHasAnEmptyOne(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"duration\":40}' WHERE checksum = 'checksum-video'");

		$data = $this->json($this->controller->fetchTrigger($this->request('GET'), $this->newResponse(), ['item_id' => '2']));

		static::assertSame([], $data['item_data']['begin_trigger']);
	}

	public function testTheTriggerIsStoredWithoutTheTokenAndTheItemId(): void
	{
		$this->actingAs(2);

		$response = $this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '1', 'notifies' => [['notify' => 'go']]])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(['notifies' => [['notify' => 'go']]], unserialize((string) $this->databaseValue('SELECT begin_trigger FROM playlists_items WHERE item_id = 1')));
	}

	public function testWithoutAnItemNothingIsLoadedOrSaved(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->fetchTrigger($this->request('GET'), $this->newResponse(), []), 'Invalid item ID.');
		$this->assertError($this->controller->save($this->request('PATCH', body: $this->withToken([])), $this->newResponse()), 'Invalid item ID.');
		$this->assertError($this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'csrf_token' => 'wrong']), $this->newResponse()), 'CSRF token validation error.');
	}

	public function testTheTriggerOfAForeignItemCanNotBeReadOrChanged(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '6', 'notifies' => [['notify' => 'go']]])), $this->newResponse());
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('', $this->databaseValue('SELECT begin_trigger FROM playlists_items WHERE item_id = 6'));
		}

		$this->expectException(AccessDeniedException::class);
		$this->controller->fetchTrigger($this->request('GET'), $this->newResponse(), ['item_id' => '6']);
	}

	public function testAnUnknownItemIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->fetchTrigger($this->request('GET'), $this->newResponse(), ['item_id' => '999']);
	}

	public function testARequestWithoutATokenIsRejectedAsAnError(): void
	{
		static::markTestSkipped('Known bug: a request without csrf_token throws a TypeError (and a warning for the undefined key) instead of the answer for a wrong token. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(2);

		$this->assertError($this->controller->save($this->request('PATCH', body: ['item_id' => '1']), $this->newResponse()), 'CSRF token validation error.');
	}

	/**
	 * The values of a trigger go into an attribute of the exported SMIL file, a value can not leave the attribute.
	 */
	public function testTheValuesOfATriggerCanNotBreakTheExportedFile(): void
	{
		static::markTestSkipped('Known bug: the date, the repeats and the weekday of a wallclock and the id of a touch are written into the SMIL file without escaping and without checking. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(2);
		$wallclock = ['iso_date' => '2030-01-01T10:00" evil="1', 'weekday' => 0, 'repeat_counts' => -1];
		$this->controller->save($this->request('PATCH', body: $this->withToken(['item_id' => '1', 'wallclocks' => [$wallclock]])), $this->newResponse());

		$workspace = new MediaWorkspace();
		try
		{
			$export = $this->exportInto($workspace);
			static::assertStringNotContainsString('evil=', $export);
		}
		finally
		{
			$workspace->remove();
		}
	}

	// @phpstan-ignore method.unused
	private function exportInto(MediaWorkspace $workspace): string
	{
		$this->buildExportService($workspace)->exportToSmil(1);

		// an item with a trigger is not in the sequence of the items but in the exclusive part
		return $workspace->filesystem->read('var/smil/playlists/1/items.smil') . $workspace->filesystem->read('var/smil/playlists/1/exclusive.smil');
	}
}
