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

namespace Tests\Browser\Modules\Templates;

use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The canvas composer of a template: it loads a template with a group and saves it.
 */
#[Group('browser')]
class TemplateComposerTest extends BrowserTestCase
{
	private const string CONTENT = '{"objects":[{"type":"group","left":10,"top":10,"objects":['
		. '{"type":"rect","left":0,"top":0,"width":100,"height":50,"fill":"#ff0000"},'
		. '{"type":"textbox","left":0,"top":60,"width":200,"text":"Hallo","fontFamily":"Arial","styles":[]}]}],'
		. '"viewport":{"width":1920,"height":1080,"scale":100}}';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->connection->insert('templates', ['template_id' => 4, 'UID' => 1, 'visibility' => 'private', 'type' => 'fabric.js', 'name' => 'Gruppe', 'content' => self::CONTENT]);
	}

	public function testATemplateWithAGroupIsLoadedAndSaved(): void
	{
		$this->login('admin');
		$this->visit('/templates/composer/4');
		$this->waitForVisible('#save');
		// the overlay stops when the template is loaded
		$this->waitForGone('#waitOverlay');

		$this->click('#save');
		$this->waitForGone('#waitOverlay');
		$this->client()->getWebDriver()->wait(10)->until(fn() => (string) $this->connection->fetchOne('SELECT content FROM templates WHERE template_id = 4') !== self::CONTENT);

		$content = (string) $this->connection->fetchOne('SELECT content FROM templates WHERE template_id = 4');
		static::assertStringContainsString('"Hallo"', $content);
		static::assertStringContainsString('"group"', $content);
		$this->assertNoErrors();
	}
}
