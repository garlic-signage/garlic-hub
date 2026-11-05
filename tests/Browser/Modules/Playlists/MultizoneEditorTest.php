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
 * The editor of the zones of a multizone playlist: a zone is added, saved and loaded again.
 */
#[Group('browser')]
class MultizoneEditorTest extends BrowserTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (4, 2, 'multizone', 'Annas Zonen')");
	}

	private function countZones(): int
	{
		return count($this->client()->getWebDriver()->findElements(WebDriverBy::cssSelector('#zones_container li')));
	}

	public function testAZoneIsSavedAndLoadedAgain(): void
	{
		$this->login('anna');
		$this->visit('/playlists/compose/4');
		$this->waitForVisible('#add_zone');
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
}
