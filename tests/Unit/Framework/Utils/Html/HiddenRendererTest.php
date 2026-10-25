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

namespace Tests\Unit\Framework\Utils\Html;

use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\HiddenField;
use App\Framework\Utils\Html\HiddenRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class HiddenRendererTest extends TestCase
{
	#[Group('units')]
	public function testRender(): void
	{
		$field = new HiddenField(['id' => 'playlist', 'type' => FieldType::HIDDEN, 'name' => 'playlist_id', 'value' => 12]);

		static::assertSame('<input type="hidden" name="playlist_id" id="playlist" value="12">', (new HiddenRenderer())->render($field));
	}

	#[Group('units')]
	public function testZeroIsRendered(): void
	{
		$field = new HiddenField(['id' => 'playlist', 'type' => FieldType::HIDDEN, 'value' => '0', 'default_value' => '9']);

		static::assertSame('<input type="hidden" name="playlist" id="playlist" value="0">', (new HiddenRenderer())->render($field));
	}
}
