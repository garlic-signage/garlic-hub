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

use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The context menu of the playlist overview and its share dialog. Users: 1 admin, 2 anna, 3 bernd.
 */
#[Group('browser')]
class PlaylistsContextMenuTest extends BrowserTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		// anna is editor of the playlists: the dialog offers her
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 2, 'playlists')");
	}

	public function testTheAdminOpensTheShareDialogOfAPlaylist(): void
	{
		$this->login('admin');
		$this->visit('/playlists');

		$this->click('.playlist-contextmenu[data-action-id="1"]');
		$this->click('.context-menu .share');

		$this->waitForVisible('dialog.unit-share');
		static::assertStringContainsString('anna', $this->text('dialog.unit-share'));
		$this->assertNoErrors();
	}
}
