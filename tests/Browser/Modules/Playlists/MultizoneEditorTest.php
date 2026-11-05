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
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverSelect;
use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The editor of the zones of a multizone playlist (4, anna). The layouts which the editor saves are compared
 * character by character: the players read them, a rebuild of the editor must save the same.
 *
 * Playlists of anna for the zones: 1 Annas Lobby, 3 Annas leere Liste.
 */
#[Group('browser')]
class MultizoneEditorTest extends BrowserTestCase
{
	/**
	 * Two zones in pixel, the z-index not in the order of the list.
	 */
	private const array PIXEL_LAYOUT = ['resolution' => '1920x1080', 'zoom' => 50, 'export_unit' => 'pixel', 'zones' => [
		['zone_name' => 'Ticker', 'zone_left' => 1280, 'zone_top' => 0, 'zone_width' => 640, 'zone_height' => 1080, 'zone_z-index' => 1, 'zone_bgcolor' => 'transparent', 'zone_playlist_id' => 0],
		['zone_name' => 'Haupt', 'zone_left' => 0, 'zone_top' => 0, 'zone_width' => 1280, 'zone_height' => 1080, 'zone_z-index' => 0, 'zone_bgcolor' => '#ff0000', 'zone_playlist_id' => 1],
	]];

	private const array PERCENT_LAYOUT = ['resolution' => '1920x1080', 'zoom' => 50, 'export_unit' => 'percent', 'zones' => [
		['zone_name' => 'Links', 'zone_left' => 0, 'zone_top' => 0, 'zone_width' => 66.666666666667, 'zone_height' => 100, 'zone_z-index' => 0, 'zone_bgcolor' => '#336699', 'zone_playlist_id' => 1],
		['zone_name' => 'Rechts', 'zone_left' => 66.666666666667, 'zone_top' => 0, 'zone_width' => 33.333333333333, 'zone_height' => 50.5, 'zone_z-index' => 1, 'zone_bgcolor' => 'rgba(0,0,0,0.5)', 'zone_playlist_id' => 0],
	]];


	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (4, 2, 'multizone', 'Annas Zonen')");
	}

	public function testAZoneIsSavedAndLoadedAgain(): void
	{
		$this->login('anna');
		$this->visit('/playlists/compose/4');
		$this->waitForEditor();
		static::assertSame(0, $this->countZones());

		$this->click('#add_zone');
		static::assertSame(1, $this->countZones());
		$this->click('#save_zones');
		$this->client()->getWebDriver()->wait(10)->until(fn() => str_contains((string) $this->connection->fetchOne('SELECT layout FROM playlists WHERE playlist_id = 4'), 'Zone 1'));

		$this->visit('/playlists/compose/4');
		$this->client()->getWebDriver()->wait(10)->until(fn() => $this->countZones() === 1);
		static::assertStringContainsString('Zone 1', $this->text('#zones_container'));

		$this->assertNoErrors();
	}

	public function testAPixelLayoutIsShownAndSavedUnchanged(): void
	{
		$this->open(self::PIXEL_LAYOUT);

		static::assertSame(['1920', '1080', 'pixel', '50'], [$this->value('screen_width'), $this->value('screen_height'), $this->value('export_unit'), $this->value('slider')]);
		static::assertSame('50 %', $this->text('#percent'));
		static::assertSame(['Haupt', 'Ticker'], $this->zoneNames());

		$this->selectZone('Haupt');
		$this->waitForValue('zone_playlist_search', 'Annas Lobby');
		static::assertSame(['Haupt', '0', '0', '1280', '1080', '#ff0000', '1'], $this->properties());
		static::assertFalse($this->checked('zone_transparent'));

		$this->selectZone('Ticker');
		$this->waitForValue('zone_playlist_search', '');
		static::assertSame(['Ticker', '1280', '0', '640', '1080'], array_slice($this->properties(), 0, 5));
		static::assertTrue($this->checked('zone_transparent'));

		static::assertSame('a:4:{s:10:"resolution";s:9:"1920x1080";s:4:"zoom";i:50;s:11:"export_unit";s:5:"pixel";s:5:"zones";a:2:{i:0;a:8:{s:9:"zone_name";s:5:"Haupt";s:9:"zone_left";i:0;s:8:"zone_top";i:0;s:10:"zone_width";i:1280;s:11:"zone_height";i:1080;s:12:"zone_z-index";i:0;s:12:"zone_bgcolor";s:7:"#ff0000";s:16:"zone_playlist_id";i:1;}i:1;a:8:{s:9:"zone_name";s:6:"Ticker";s:9:"zone_left";i:1280;s:8:"zone_top";i:0;s:10:"zone_width";i:640;s:11:"zone_height";i:1080;s:12:"zone_z-index";i:1;s:12:"zone_bgcolor";s:11:"transparent";s:16:"zone_playlist_id";i:0;}}}', $this->saveAndLoadLayout());
		$this->assertNoErrors();
	}

	public function testAPercentLayoutIsShownInPixelAndSavedUnchanged(): void
	{
		$this->open(self::PERCENT_LAYOUT);

		static::assertSame(['1920', '1080', 'percent', '50'], [$this->value('screen_width'), $this->value('screen_height'), $this->value('export_unit'), $this->value('slider')]);
		static::assertSame(['Links', 'Rechts'], $this->zoneNames());

		$this->selectZone('Rechts');
		static::assertSame(['Rechts', '1280', '0', '640', '545'], array_slice($this->properties(), 0, 5));

		static::assertSame('a:4:{s:10:"resolution";s:9:"1920x1080";s:4:"zoom";i:50;s:11:"export_unit";s:7:"percent";s:5:"zones";a:2:{i:0;a:8:{s:9:"zone_name";s:5:"Links";s:9:"zone_left";i:0;s:8:"zone_top";i:0;s:10:"zone_width";d:66.66666666666667;s:11:"zone_height";i:100;s:12:"zone_z-index";i:0;s:12:"zone_bgcolor";s:7:"#336699";s:16:"zone_playlist_id";i:1;}i:1;a:8:{s:9:"zone_name";s:6:"Rechts";s:9:"zone_left";d:66.66666666666667;s:8:"zone_top";i:0;s:10:"zone_width";d:33.333333333333336;s:11:"zone_height";d:50.46296296296296;s:12:"zone_z-index";i:1;s:12:"zone_bgcolor";s:15:"rgba(0,0,0,0.5)";s:16:"zone_playlist_id";i:0;}}}', $this->saveAndLoadLayout());
		$this->assertNoErrors();
	}

	public function testThePropertiesChangeTheZones(): void
	{
		$this->open(self::PIXEL_LAYOUT);

		$this->selectZone('Haupt');
		$this->waitForValue('zone_playlist_search', 'Annas Lobby');
		$this->type('zone_name', 'Main');
		$this->type('zone_left', '10', true);
		$this->type('zone_top', '20', true);
		$this->type('zone_width', '1000', true);
		$this->type('zone_height', '500', true);
		$this->setColor('#00ff00');
		// without a name the zone has no playlist
		$this->type('zone_playlist_search', '', true);
		static::assertSame(['Main', 'Ticker'], $this->zoneNames());

		$this->selectZone('Ticker');
		$this->element('zone_transparent')->click();
		$this->choosePlaylist('Annas leere Liste');

		static::assertSame('a:4:{s:10:"resolution";s:9:"1920x1080";s:4:"zoom";i:50;s:11:"export_unit";s:5:"pixel";s:5:"zones";a:2:{i:0;a:8:{s:9:"zone_name";s:4:"Main";s:9:"zone_left";i:10;s:8:"zone_top";i:20;s:10:"zone_width";i:1000;s:11:"zone_height";i:500;s:12:"zone_z-index";i:0;s:12:"zone_bgcolor";s:7:"#00ff00";s:16:"zone_playlist_id";i:0;}i:1;a:8:{s:9:"zone_name";s:6:"Ticker";s:9:"zone_left";i:1280;s:8:"zone_top";i:0;s:10:"zone_width";i:640;s:11:"zone_height";i:1080;s:12:"zone_z-index";i:1;s:12:"zone_bgcolor";s:7:"#000000";s:16:"zone_playlist_id";i:3;}}}', $this->saveAndLoadLayout());
		$this->assertNoErrors();
	}

	public function testTheKeyboardAndTheContextMenuChangeTheZones(): void
	{
		$this->open(self::PIXEL_LAYOUT);

		// Haupt: a step to the right, a big step down, then a copy which is put to the background
		$this->clickCanvas(640, 540);
		$this->pressKeys(WebDriverKeys::ARROW_RIGHT);
		$this->pressKeys([WebDriverKeys::SHIFT, WebDriverKeys::ARROW_DOWN]);
		$this->pressKeys([WebDriverKeys::CONTROL, 'd']);
		static::assertSame(['Haupt', 'Ticker', 'Haupt'], $this->zoneNames());
		static::assertSame(['Haupt', '21', '70'], array_slice($this->properties(), 0, 3));

		$this->contextMenu(700, 700, '#move_background');

		// Ticker is deleted with the context menu
		$this->contextMenu(1600, 540, '#delete_zone');
		static::assertSame(['Haupt', 'Haupt'], $this->zoneNames());

		// a copy with the context menu, removed with the key
		$this->contextMenu(100, 100, '#duplicate_zone');
		static::assertSame(['Haupt', 'Haupt', 'Haupt'], $this->zoneNames());
		// the keys go to the canvas: the copy lies on top
		$this->clickCanvas(100, 100);
		$this->pressKeys(WebDriverKeys::DELETE);
		static::assertSame(['Haupt', 'Haupt'], $this->zoneNames());

		static::assertSame('a:4:{s:10:"resolution";s:9:"1920x1080";s:4:"zoom";i:50;s:11:"export_unit";s:5:"pixel";s:5:"zones";a:2:{i:0;a:8:{s:9:"zone_name";s:5:"Haupt";s:9:"zone_left";i:21;s:8:"zone_top";i:70;s:10:"zone_width";i:1280;s:11:"zone_height";i:1080;s:12:"zone_z-index";i:0;s:12:"zone_bgcolor";s:7:"#ff0000";s:16:"zone_playlist_id";i:1;}i:1;a:8:{s:9:"zone_name";s:5:"Haupt";s:9:"zone_left";i:1;s:8:"zone_top";i:50;s:10:"zone_width";i:1280;s:11:"zone_height";i:1080;s:12:"zone_z-index";i:1;s:12:"zone_bgcolor";s:7:"#ff0000";s:16:"zone_playlist_id";i:1;}}}', $this->saveAndLoadLayout());
		$this->assertNoErrors();
	}

	public function testAZoneIsMovedWithTheMouseOnTheGrid(): void
	{
		$this->open(self::PIXEL_LAYOUT);

		$this->pointTo(1600, 540);
		// 103 and 47 pixel of the screen are 206 and 94 on the canvas with the zoom 50 %
		$this->actions()->clickAndHold()->moveByOffset(40, 20)->moveByOffset(63, 27)->release()->perform();
		static::assertSame(['Ticker', '1490', '90'], array_slice($this->properties(), 0, 3));

		static::assertSame('a:4:{s:10:"resolution";s:9:"1920x1080";s:4:"zoom";i:50;s:11:"export_unit";s:5:"pixel";s:5:"zones";a:2:{i:0;a:8:{s:9:"zone_name";s:5:"Haupt";s:9:"zone_left";i:0;s:8:"zone_top";i:0;s:10:"zone_width";i:1280;s:11:"zone_height";i:1080;s:12:"zone_z-index";i:0;s:12:"zone_bgcolor";s:7:"#ff0000";s:16:"zone_playlist_id";i:1;}i:1;a:8:{s:9:"zone_name";s:6:"Ticker";s:9:"zone_left";i:1490;s:8:"zone_top";i:90;s:10:"zone_width";i:640;s:11:"zone_height";i:1080;s:12:"zone_z-index";i:1;s:12:"zone_bgcolor";s:11:"transparent";s:16:"zone_playlist_id";i:0;}}}', $this->saveAndLoadLayout());
		$this->assertNoErrors();
	}

	public function testTheUnitTheResolutionAndTheZoomAreSaved(): void
	{
		$this->open(self::PIXEL_LAYOUT);

		(new WebDriverSelect($this->element('export_unit')))->selectByValue('percent');
		$this->type('screen_width', '2560', true);
		$this->type('screen_height', '1440', true);
		$this->client()->executeScript("const slider = document.getElementById('slider'); slider.value = 75; slider.dispatchEvent(new Event('input'));");
		static::assertSame('75 %', $this->text('#percent'));

		static::assertSame('a:4:{s:10:"resolution";s:9:"2560x1440";s:4:"zoom";i:75;s:11:"export_unit";s:7:"percent";s:5:"zones";a:2:{i:0;a:8:{s:9:"zone_name";s:5:"Haupt";s:9:"zone_left";i:0;s:8:"zone_top";i:0;s:10:"zone_width";i:50;s:11:"zone_height";i:75;s:12:"zone_z-index";i:0;s:12:"zone_bgcolor";s:7:"#ff0000";s:16:"zone_playlist_id";i:1;}i:1;a:8:{s:9:"zone_name";s:6:"Ticker";s:9:"zone_left";i:50;s:8:"zone_top";i:0;s:10:"zone_width";i:25;s:11:"zone_height";i:75;s:12:"zone_z-index";i:1;s:12:"zone_bgcolor";s:11:"transparent";s:16:"zone_playlist_id";i:0;}}}', $this->saveAndLoadLayout());
		$this->assertNoErrors();
	}

	public function testTheEditorAsksBeforeItClosesWithChanges(): void
	{
		$this->open(self::PIXEL_LAYOUT);
		$driver = $this->client()->getWebDriver();

		$this->click('#add_zone');
		$this->click('#close_zones_editor');
		$driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
		$driver->switchTo()->alert()->dismiss();
		static::assertStringContainsString('/playlists/compose/4', $driver->getCurrentURL());

		// saved: no question
		$this->connection->executeStatement("UPDATE playlists SET layout = '' WHERE playlist_id = 4");
		$this->click('#save_zones');
		$driver->wait(10)->until(fn() => $this->layout() !== '');
		$this->waitForGone('#waitOverlay');
		$this->click('#close_zones_editor');
		$driver->wait(5)->until(fn() => str_ends_with($driver->getCurrentURL(), '/playlists'));

		$this->assertNoErrors();
	}

	public function testTheEditorClosesWithoutChangesAndAfterTheQuestion(): void
	{
		$this->open(self::PIXEL_LAYOUT);
		$driver = $this->client()->getWebDriver();

		$this->click('#close_zones_editor');
		$driver->wait(5)->until(fn() => str_ends_with($driver->getCurrentURL(), '/playlists'));

		$this->visit('/playlists/compose/4');
		$this->waitForEditor();
		$this->click('#add_zone');
		$this->click('#close_zones_editor');
		$driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
		$driver->switchTo()->alert()->accept();
		$driver->wait(5)->until(fn() => str_ends_with($driver->getCurrentURL(), '/playlists'));
	}

	/**
	 * @param array<mixed> $layout
	 */
	private function open(array $layout): void
	{
		$this->connection->update('playlists', ['layout' => serialize($layout)], ['playlist_id' => 4]);
		$this->login('anna');
		$this->visit('/playlists/compose/4');
		$this->waitForEditor();
		static::assertSame(count($layout['zones']), $this->countZones());
	}

	/**
	 * The editor fills the resolution when the zones are built.
	 */
	private function waitForEditor(): void
	{
		$this->client()->getWebDriver()->wait(10)->until(fn() => $this->value('screen_width') !== '');
	}

	private function layout(): string
	{
		return (string) $this->connection->fetchOne('SELECT layout FROM playlists WHERE playlist_id = 4');
	}

	/**
	 * Saves and returns what the server stored. The layout is emptied before, a save of the same layout is seen, too.
	 */
	private function saveAndLoadLayout(): string
	{
		$this->connection->executeStatement("UPDATE playlists SET layout = '' WHERE playlist_id = 4");
		$this->click('#save_zones');
		$this->client()->getWebDriver()->wait(10)->until(fn() => $this->layout() !== '');

		return $this->layout();
	}

	private function countZones(): int
	{
		return count($this->client()->getWebDriver()->findElements(WebDriverBy::cssSelector('#zones_container li')));
	}

	/**
	 * @return list<string>
	 */
	private function zoneNames(): array
	{
		$names = [];
		foreach ($this->client()->getWebDriver()->findElements(WebDriverBy::cssSelector('#zones_container li')) as $item)
			$names[] = $item->getText();

		return $names;
	}

	private function selectZone(string $name): void
	{
		$this->client()->getWebDriver()->findElement(WebDriverBy::xpath("//fieldset[@id='zones_container']//li[text()='" . $name . "']"))->click();
		$this->client()->getWebDriver()->wait(5)->until(fn() => $this->value('zone_name') === $name);
	}

	/**
	 * @return list<string> name, left, top, width, height, color, playlist
	 */
	private function properties(): array
	{
		$values = [];
		foreach (['zone_name', 'zone_left', 'zone_top', 'zone_width', 'zone_height', 'zone_bgcolor', 'zone_playlist'] as $id)
			$values[] = $this->value($id);

		return $values;
	}

	private function element(string $id): WebDriverElement
	{
		return $this->client()->getWebDriver()->findElement(WebDriverBy::id($id));
	}

	private function value(string $id): string
	{
		return (string) $this->client()->executeScript('return document.getElementById(arguments[0]).value;', [$id]);
	}

	private function checked(string $id): bool
	{
		return (bool) $this->client()->executeScript('return document.getElementById(arguments[0]).checked;', [$id]);
	}

	private function waitForValue(string $id, string $value): void
	{
		$this->client()->getWebDriver()->wait(5)->until(fn() => $this->value($id) === $value);
	}

	/**
	 * Replaces the text of a field, with $leave the field loses the focus: the number fields take the value on blur.
	 */
	private function type(string $id, string $text, bool $leave = false): void
	{
		$keys = [[WebDriverKeys::CONTROL, 'a'], WebDriverKeys::BACKSPACE, $text];
		if ($leave)
			$keys[] = WebDriverKeys::TAB;

		$this->element($id)->sendKeys($keys);
	}

	/**
	 * The color picker has no keyboard: the value is set like the picker does.
	 */
	private function setColor(string $color): void
	{
		$this->client()->executeScript("const picker = document.getElementById('zone_bgcolor'); picker.value = arguments[0]; picker.dispatchEvent(new Event('input'));", [$color]);
	}

	/**
	 * Types the name, waits for the suggestion and leaves the field: the autocomplete takes a suggestion with the same name.
	 */
	private function choosePlaylist(string $name): void
	{
		$this->type('zone_playlist_search', $name);
		$this->client()->getWebDriver()->wait(5)->until(fn() => (bool) $this->client()->executeScript(
			'return document.querySelector(\'#zone_playlist_suggestions option[value="\' + arguments[0] + \'"]\') !== null;', [$name]));
		$this->element('zone_playlist_search')->sendKeys(WebDriverKeys::TAB);
		$this->waitForValue('zone_playlist', '3');
	}

	/**
	 * Moves the mouse to a point of the screen of the playlist, the canvas shows it with the zoom.
	 */
	private function pointTo(int $x, int $y): void
	{
		$canvas = $this->client()->getWebDriver()->findElement(WebDriverBy::cssSelector('#canvas_wrap .upper-canvas'));
		$zoom   = (int) $this->value('slider') / 100;
		$size   = $canvas->getSize();
		// the origin of the element is its center
		$this->actions()->moveToElement($canvas, (int) round($x * $zoom - $size->getWidth() / 2), (int) round($y * $zoom - $size->getHeight() / 2))->perform();
	}

	private function clickCanvas(int $x, int $y): void
	{
		$this->pointTo($x, $y);
		$this->actions()->click()->perform();
	}

	private function contextMenu(int $x, int $y, string $entry): void
	{
		$this->pointTo($x, $y);
		$this->actions()->contextClick()->perform();
		$this->click($entry);
	}

	/**
	 * @param string|list<string> $keys
	 */
	private function pressKeys(string|array $keys): void
	{
		$this->client()->getWebDriver()->getKeyboard()->sendKeys($keys);
	}
}
