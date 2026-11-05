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

namespace Tests\Browser\Modules\Playlists;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverSelect;
use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The composer of a playlist: insert by drag and drop, change a duration, save an editor.
 * Playlist 1 of anna has 5 items. The selector offers the internal playlists: 2 of bernd, the admin may use it.
 */
#[Group('browser')]
class PlaylistComposerTest extends BrowserTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
	}

	private function countItems(): int
	{
		return count($this->client()->getWebDriver()->findElements(WebDriverBy::cssSelector('#thePlaylist li.playlist-item')));
	}

	public function testAPlaylistIsInsertedTheDurationChangedAndAnEditorSaved(): void
	{
		$this->login('admin');
		$this->visit('/playlists/compose/1');
		$this->waitForVisible('#thePlaylist li.playlist-item');
		static::assertSame(5, $this->countItems());

		// insert: the selector of the playlists, then playlist 2 into the list
		$driver = $this->client()->getWebDriver();
		new WebDriverSelect($driver->findElement(WebDriverBy::id('insertMenuSelect')))->selectByValue('insertPlaylists');
		$this->waitForVisible('#playlistsList [data-select-id="2"]');
		$source = $driver->findElement(WebDriverBy::cssSelector('#playlistsList [data-select-id="2"]'));
		$target = $driver->findElement(WebDriverBy::cssSelector('#thePlaylist li.playlist-item:last-child'));
		// dragula starts with a move after the mouse down
		$this->actions()->clickAndHold($source)->moveByOffset(10, 10)->moveToElement($target, 0, 20)->release()->perform();
		$driver->wait(10)->until(fn() => $this->countItems() === 6);
		static::assertSame(6, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 1'));

		// the duration of the image
		$this->click('#itemId-1 .item-duration');
		$input = $driver->findElement(WebDriverBy::cssSelector('#itemId-1 .item-duration-input input'));
		// clear() would blur the field: an empty duration is saved and refused
		$input->sendKeys([WebDriverKeys::CONTROL, 'a']);
		$input->sendKeys('20' . WebDriverKeys::ENTER);
		$driver->wait(10)->until(fn() => $this->text('#itemId-1 .item-duration') === '00:00:20');
		static::assertSame(20, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 1'));

		// the editor of the conditional play opens, saves and closes
		$this->click('#itemId-1 .conditional-play');
		$this->waitForVisible('#editItemDialog');
		$this->click('#editItemForm button[type="submit"]');
		$this->waitForGone('#editItemDialog');

		$this->assertNoErrors();
	}
}
