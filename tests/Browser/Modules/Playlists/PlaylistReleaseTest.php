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
use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The release of playlists in the composer: while it is on, anna asks for the release of her playlist 1
 * with the export button, a change asks for it again, and the admin sees on the home page what waits.
 */
#[Group('browser')]
class PlaylistReleaseTest extends BrowserTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->deployment()->changeSettings('playlists', ['Release' => ['enabled' => '1']]);
	}

	private function releaseStatus(): string
	{
		return (string) $this->connection->fetchOne('SELECT release_status FROM playlists WHERE playlist_id = 1');
	}

	private function exportButtonIsEnabled(): bool
	{
		return $this->client()->getWebDriver()->findElement(WebDriverBy::id('playerExport'))->isEnabled();
	}

	private function openComposer(): void
	{
		$this->visit('/playlists/compose/1');
		$this->waitForVisible('#thePlaylist li.playlist-item');
	}

	public function testTheOwnerAsksForTheReleaseAndAChangeAsksAgain(): void
	{
		$this->login('anna');
		$this->openComposer();
		static::assertSame('Ready for release', $this->text('#playerExport'));
		static::assertTrue($this->exportButtonIsEnabled());

		$this->click('#playerExport');
		$driver = $this->client()->getWebDriver();
		$driver->wait(10)->until(fn() => $this->text('#playerExport') === 'Waiting for release');
		static::assertFalse($this->exportButtonIsEnabled());
		static::assertSame('ready', $this->releaseStatus());

		// the page knows the status after a reload, too
		$this->openComposer();
		$driver->wait(10)->until(fn() => $this->text('#playerExport') === 'Waiting for release');
		static::assertFalse($this->exportButtonIsEnabled());

		// a change: the release is asked for again
		$this->click('#toggleShuffle');
		$driver->wait(10)->until(fn() => $this->text('#playerExport') === 'Ready for release');
		static::assertTrue($this->exportButtonIsEnabled());
		static::assertSame('in_progress', $this->releaseStatus());
		static::assertFileDoesNotExist($this->deployment()->directory() . '/var/smil/playlists/1/items.smil');

		$this->assertNoErrors();
	}

	public function testAReleasedPlaylistAsksForNothingUntilItChanges(): void
	{
		$this->connection->executeStatement("UPDATE playlists SET release_status = 'released' WHERE playlist_id = 1");
		$this->login('anna');
		$this->openComposer();

		$driver = $this->client()->getWebDriver();
		$driver->wait(10)->until(fn() => $this->text('#playerExport') === 'Released');
		static::assertFalse($this->exportButtonIsEnabled());
		static::assertStringNotContainsString('notify-save', (string) $driver->findElement(WebDriverBy::id('playerExport'))->getAttribute('class'));

		// a change: the release is asked for again
		$this->click('#toggleShuffle');
		$driver->wait(10)->until(fn() => $this->text('#playerExport') === 'Ready for release');
		static::assertTrue($this->exportButtonIsEnabled());
		static::assertStringContainsString('notify-save', (string) $driver->findElement(WebDriverBy::id('playerExport'))->getAttribute('class'));
		static::assertSame('in_progress', $this->releaseStatus());

		$this->assertNoErrors();
	}

	public function testTheAdminSeesWhatWaitsAndExports(): void
	{
		$this->connection->executeStatement("UPDATE playlists SET release_status = 'ready' WHERE playlist_id = 1");
		$this->login('admin');

		$this->visit('/');
		$this->waitForVisible('.dashboards');
		static::assertMatchesRegularExpression('/Waiting for release:\s*1/', $this->text('.dashboards'));

		// the number leads to the list with only the waiting playlist
		$this->click('.dashboards a[href="/playlists?release_status=ready"]');
		$this->waitForVisible('#playlist_name_1');
		$driver = $this->client()->getWebDriver();
		static::assertCount(0, $driver->findElements(WebDriverBy::id('playlist_name_3')));
		static::assertSame('ready', $driver->findElement(WebDriverBy::id('release_status'))->getAttribute('value'));
		static::assertSame('Waiting for release', $this->text('.results-body[data-id="1"] li.release_status'));

		// the composer shows the admin that the playlist waits, the export releases it
		$this->openComposer();
		static::assertSame('Export for Player', $this->text('#playerExport'));
		$this->waitForVisible('#releaseWaiting');
		$this->click('#playerExport');
		$driver->wait(10)->until(fn() => $this->releaseStatus() === 'released');
		$this->waitForGone('#releaseWaiting');
		static::assertFileExists($this->deployment()->directory() . '/var/smil/playlists/1/items.smil');

		$this->assertNoErrors();
	}
}
