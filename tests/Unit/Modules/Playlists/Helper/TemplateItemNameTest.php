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

namespace Tests\Unit\Modules\Playlists\Helper;

use App\Modules\Playlists\Helper\TemplateItemName;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TemplateItemNameTest extends TestCase
{
	private TemplateItemName $templateItemName;

	protected function setUp(): void
	{
		parent::setUp();
		$this->templateItemName = new TemplateItemName('unit-test-secret');
	}

	#[Group('units')]
	public function testTheNameIsTheItemIdAndAHmacOfTheContent(): void
	{
		$expected = '13-' . substr(hash_hmac('sha256', '13|{"objects":[]}', 'unit-test-secret'), 0, 32);

		static::assertSame($expected, $this->templateItemName->build(13, '{"objects":[]}'));
		static::assertMatchesRegularExpression('/^\d{1,10}-[a-f0-9]{32}$/', $this->templateItemName->build(13, ''));
	}

	#[Group('units')]
	public function testTheSameContentKeepsTheName(): void
	{
		static::assertSame($this->templateItemName->build(13, 'content'), $this->templateItemName->build(13, 'content'));
	}

	#[Group('units')]
	public function testAnotherContentGetsAnotherName(): void
	{
		static::assertNotSame($this->templateItemName->build(13, 'content'), $this->templateItemName->build(13, 'changed'));
	}

	/**
	 * Two items with the same content, e.g. inserted from the same template, do not share a name.
	 */
	#[Group('units')]
	public function testAnotherItemWithTheSameContentGetsAnotherName(): void
	{
		static::assertNotSame(substr($this->templateItemName->build(13, 'content'), 3), substr($this->templateItemName->build(14, 'content'), 3));
	}

	/**
	 * Without the secret the name can not be built from the item id and a known content.
	 */
	#[Group('units')]
	public function testTheNameDependsOnTheSecret(): void
	{
		static::assertNotSame((new TemplateItemName('other-secret'))->build(13, 'content'), $this->templateItemName->build(13, 'content'));
	}
}
