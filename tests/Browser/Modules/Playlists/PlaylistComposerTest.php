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

	public function testThePreviewGoesThroughThePlaylistAndListsANestedPlaylist(): void
	{
		$this->login('admin');
		$this->visit('/playlists/compose/1');
		$this->waitForVisible('#thePlaylist li.playlist-item');
		$driver = $this->client()->getWebDriver();

		// the nested playlist 2 of bernd shows only the list of its items
		$this->click('#itemId-3 .item-preview');
		$this->waitForVisible('.gslide.current .preview-nested-playlist li');
		$driver->wait(10)->until(fn() => count($driver->findElements(WebDriverBy::cssSelector('.gslide.current .preview-nested-playlist li'))) === 2);
		static::assertStringContainsString('Annas leere Liste', $this->text('.gslide.current .preview-nested-playlist'));

		// forward to the pdf and back again. The video is left out: without its file the browser complains about
		// the interrupted autoplay
		$this->client()->getKeyboard()->sendKeys(WebDriverKeys::ARROW_RIGHT);
		$driver->wait(10)->until(fn() => str_contains((string) $this->client()->executeScript('return document.querySelector(".gslide.current iframe")?.src ?? ""'), '/media.php/orig/checksum-pdf.pdf'));
		// an iframe has no size of its own: beside the title it must still fill the lightbox
		$height = (int) $this->client()->executeScript('return document.querySelector(".gslide.current iframe").getBoundingClientRect().height');
		static::assertGreaterThan(400, $height);
		$this->client()->getKeyboard()->sendKeys(WebDriverKeys::ARROW_LEFT);
		$this->waitForVisible('.gslide.current .preview-nested-playlist');

		$this->client()->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
		$this->waitForGone('.glightbox-container');

		$this->assertNoErrors();
	}

	public function testHoveringATemplateOfTheSelectorShowsItsThumbnail(): void
	{
		$this->loadFixture('templates/templates.sql');
		$this->login('admin');
		$this->visit('/playlists/compose/1');
		$this->waitForVisible('#thePlaylist li.playlist-item');
		$driver = $this->client()->getWebDriver();

		new WebDriverSelect($driver->findElement(WebDriverBy::id('insertMenuSelect')))->selectByValue('insertTemplates');
		$this->waitForVisible('#templatesList .template-thumbnail');
		$this->actions()->moveToElement($driver->findElement(WebDriverBy::cssSelector('#templatesList .template-thumbnail')))->perform();

		// only the preview of the templates appears, the one of the media and the one of the playlist stay hidden
		$this->waitForVisible('.thumbnail-preview.visible');
		static::assertCount(1, $driver->findElements(WebDriverBy::cssSelector('.thumbnail-preview.visible')));
		static::assertStringContainsString('/media.php/tpl-thumb/', (string) $driver->findElement(WebDriverBy::cssSelector('.thumbnail-preview.visible'))->getAttribute('src'));

		$this->actions()->moveToElement($driver->findElement(WebDriverBy::cssSelector('#playlistDuration')))->perform();
		$this->waitForGone('.thumbnail-preview.visible');
	}
}
