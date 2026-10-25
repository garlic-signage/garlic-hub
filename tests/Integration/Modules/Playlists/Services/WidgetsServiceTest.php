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

namespace Tests\Integration\Modules\Playlists\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Playlists\Services\WidgetsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The settings of a widget item: the preferences come from the config.xml of the widget in the mediapool,
 * the values are stored serialized in the item. Fixture: the item 1 of anna's playlist 1 is made a widget.
 */
#[Group('acl')]
class WidgetsServiceTest extends ContainerTestCase
{
	private const string CONFIG = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<widget xmlns="http://www.w3.org/ns/widgets" xmlns:ia="http://schemas.iawidget.org/widgetconfig-1.0">'
		. '<preference name="urls" ia:types="text" ia:mandatory="true" />'
		. '<preference name="count" ia:types="integer" />'
		. '<preference name="fixed" value="1" readonly="true" />'
		. '</widget>';

	private WidgetsService $widgetsService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->connection->executeStatement("UPDATE playlists_items SET mimetype = 'application/widget' WHERE item_id = 1");
		$this->connection->executeStatement(
			"UPDATE mediapool_files SET mimetype = 'application/widget', config_data = ?, metadata = '{}' WHERE checksum = 'checksum-image'",
			[self::CONFIG]
		);
		$this->widgetsService = $this->container->get(WidgetsService::class);
	}

	public function testTheEditablePreferencesAreTheOnesOfTheConfig(): void
	{
		$this->actingAs(2);

		$widget = $this->widgetsService->fetchWidgetByItemId(1);

		static::assertSame(1, $widget['item_id']);
		static::assertSame('bild.jpg', $widget['item_name']);
		static::assertSame([], $widget['values']);
		static::assertSame(['urls', 'count'], array_keys($widget['preferences']));
		static::assertSame('true', $widget['preferences']['urls']['mandatory']);
	}

	public function testSavedValuesComeBackAndNumbersAreCast(): void
	{
		$this->actingAs(2);

		$this->widgetsService->saveWidget(1, ['urls' => 'http://example.com/feed', 'count' => '12']);

		static::assertSame(['urls' => 'http://example.com/feed', 'count' => 12], $this->widgetsService->fetchWidgetByItemId(1)['values']);
	}

	public function testAMandatoryValueMustBeGiven(): void
	{
		$this->actingAs(2);

		try
		{
			$this->widgetsService->saveWidget(1, ['count' => '12']);
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('widget_field_mandatory', $e->getTranslationKey());
			static::assertSame('', $this->connection->fetchOne('SELECT content_data FROM playlists_items WHERE item_id = 1'));
		}
	}

	public function testAnItemWhichIsNoWidgetCanNotBeEdited(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"duration\":30}' WHERE checksum = 'checksum-video'");

		try
		{
			$this->widgetsService->fetchWidgetByItemId(2);
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('item_not_widget', $e->getTranslationKey());
		}
	}

	public function testAUserMayNotChangeTheWidgetOfAForeignPlaylist(): void
	{
		$this->actingAs(3);

		$this->expectException(AccessDeniedException::class);
		$this->widgetsService->saveWidget(1, ['urls' => 'http://example.com/feed']);
	}
}
