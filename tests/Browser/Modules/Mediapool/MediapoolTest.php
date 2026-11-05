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

namespace Tests\Browser\Modules\Mediapool;

use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The mediapool: a folder is added, a file uploaded and its information shown.
 * anna has the user folder 7 with 8 Fotos and 9 Archiv.
 */
#[Group('browser')]
class MediapoolTest extends BrowserTestCase
{
	private string $picture = '';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');

		// a picture of one pixel
		$this->picture = sys_get_temp_dir() . '/pixel-' . bin2hex(random_bytes(4)) . '.png';
		file_put_contents($this->picture, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
	}

	protected function tearDown(): void
	{
		@unlink($this->picture);
		parent::tearDown();
	}

	private function folder(string $name): string
	{
		return "//span[contains(@class, 'wb-title') and text()='" . $name . "']";
	}

	private function countMedia(): int
	{
		return count($this->client()->getWebDriver()->findElements(WebDriverBy::cssSelector('#mediaList .media-item')));
	}

	public function testAFolderIsAddedAFileUploadedAndItsInformationShown(): void
	{
		$this->login('anna');
		$this->visit('/mediapool');
		$driver = $this->client()->getWebDriver();
		// the sub folders are loaded when the user folder opens
		$this->client()->waitFor($this->folder('anna'), 10);
		$driver->findElement(WebDriverBy::xpath($this->folder('anna') . "/preceding-sibling::i[contains(@class, 'wb-expander')]"))->click();
		$this->client()->waitFor($this->folder('Fotos'), 10);
		$fotos = $driver->findElement(WebDriverBy::xpath($this->folder('Fotos')));
		$fotos->click();

		// a sub folder by the context menu of the tree
		$this->actions()->contextClick($fotos)->perform();
		$this->click('#add_node');
		$this->waitForVisible('#editFolderDialog');
		$driver->findElement(WebDriverBy::id('folderName'))->sendKeys('Urlaub');
		$this->click('#editFolderDialog button[value="submit"]');
		$driver->wait(10)->until(fn() => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM mediapool_nodes WHERE name = 'Urlaub' AND parent_id = 8") === 1);

		// the upload into Fotos
		$driver->findElement(WebDriverBy::xpath($this->folder('Fotos')))->click();
		$this->click('#openUploadDialog');
		$this->waitForVisible('#uploaderDialog');
		$driver->findElement(WebDriverBy::id('fileInput'))->sendKeys($this->picture);
		$this->click('#startLocalFilesUpload');
		$driver->wait(15)->until(fn() => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE node_id = 8') === 1);
		$this->click('#closeUploadDialog');
		$driver->wait(10)->until(fn() => $this->countMedia() === 1);

		// the information of the media
		$this->actions()->contextClick($driver->findElement(WebDriverBy::cssSelector('#mediaList .media-item')))->perform();
		$this->click('#infoMedia');
		$this->waitForVisible('#editInfoDialog');
		static::assertSame(basename($this->picture), $this->text('#editInfoDialog .media-filename'));

		$this->assertNoErrors();
	}
}
