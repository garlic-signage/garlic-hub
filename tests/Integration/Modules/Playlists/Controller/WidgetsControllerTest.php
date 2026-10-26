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
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Playlists\Controller\WidgetsController;
use PHPUnit\Framework\Attributes\Group;

/**
 * The settings of a widget item /async/playlists/widget: the preferences come from the config.xml of the widget.
 */
#[Group('acl')]
class WidgetsControllerTest extends PlaylistsControllerTestCase
{
	private const string CONFIG = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<widget xmlns="http://www.w3.org/ns/widgets" xmlns:ia="http://schemas.iawidget.org/widgetconfig-1.0">'
		. '<preference name="urls" ia:types="text" ia:mandatory="true" />'
		. '<preference name="count" ia:types="integer" />'
		. '</widget>';

	private WidgetsController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection->executeStatement("UPDATE playlists_items SET mimetype = 'application/widget' WHERE item_id = 1");
		$this->connection->executeStatement("UPDATE mediapool_files SET mimetype = 'application/widget', config_data = ? WHERE checksum = 'checksum-image'", [self::CONFIG]);
		$this->controller = $this->container->get(WidgetsController::class);
	}

	public function testTheWidgetComesWithItsPreferences(): void
	{
		$this->actingAs(2);

		$response = $this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '1']);

		$this->assertSuccess($response);
		$data = $this->json($response)['data'];
		static::assertSame(['urls', 'count'], array_keys($data['preferences']));
		static::assertSame([], $data['values']);
		static::assertSame('bild.jpg', $data['item_name']);
	}

	public function testTheValuesAreSavedAndNumbersAreCast(): void
	{
		$this->actingAs(2);

		$saved = $this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'urls' => 'http://example.com/feed', 'count' => '12']), $this->newResponse());

		$this->assertSuccess($saved);
		$values = unserialize((string) $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 1'));
		static::assertSame('http://example.com/feed', $values['urls']);
		static::assertSame(12, $values['count']);
	}

	public function testAMandatoryValueMustBeGiven(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'count' => '12']), $this->newResponse());
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('widget_field_mandatory', $e->getTranslationKey());
			static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 1'));
		}
	}

	public function testOnlyTheValuesOfTheWidgetAreStored(): void
	{
		$this->actingAs(2);

		$this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'urls' => 'http://example.com/feed', 'unknown' => 'x']), $this->newResponse());

		static::assertSame(['urls' => 'http://example.com/feed'], unserialize((string) $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 1')));
	}

	/**
	 * A zero or an emptied text is a value of the user, it does not fall back to the default of the widget.
	 */
	public function testEmptyValuesAreStored(): void
	{
		$this->actingAs(2);

		$this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'urls' => 'http://example.com/feed', 'count' => '0']), $this->newResponse());

		static::assertSame(['urls' => 'http://example.com/feed', 'count' => 0], unserialize((string) $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 1')));
	}

	public function testAPreferenceWithoutATypeIsSavedAndLoadedAsText(): void
	{
		$this->actingAs(2);
		$plain = '<?xml version="1.0" encoding="UTF-8"?><widget xmlns="http://www.w3.org/ns/widgets"><preference name="url" /></widget>';
		$this->connection->executeStatement("UPDATE mediapool_files SET config_data = ? WHERE checksum = 'checksum-image'", [$plain]);

		$saved = $this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'url' => 'http://example.com']), $this->newResponse());
		$loaded = $this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '1']);

		$this->assertSuccess($saved);
		static::assertSame(['url' => 'http://example.com'], $this->json($loaded)['data']['values']);
		static::assertSame(['url'], array_keys($this->json($loaded)['data']['preferences']));
	}

	public function testWithoutAnItemNothingIsLoadedOrSaved(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->fetch($this->request('GET'), $this->newResponse(), []), 'Item ID not valid.');
		$this->assertError($this->controller->save($this->request('PATCH', body: ['urls' => 'x']), $this->newResponse()), 'Item ID not valid.');
		static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 1'));
	}

	public function testAnItemWhichIsNoWidgetHasNoSettings(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"duration\":40}' WHERE checksum = 'checksum-video'");

		$this->expectException(TranslatableModuleException::class);
		$this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '2']);
	}

	public function testTheWidgetOfAForeignPlaylistCanNotBeReadOrChanged(): void
	{
		$this->actingAs(3);

		try
		{
			$this->controller->save($this->request('PATCH', body: ['item_id' => '1', 'urls' => 'x']), $this->newResponse());
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('', $this->databaseValue('SELECT content_data FROM playlists_items WHERE item_id = 1'));
		}

		$this->expectException(AccessDeniedException::class);
		$this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '1']);
	}

	public function testWithoutABodyNothingIsDone(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->save($this->request('PATCH')->withParsedBody(null), $this->newResponse()), 'Item ID not valid.');
	}
}
