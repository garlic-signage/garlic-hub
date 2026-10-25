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
use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\UrlField;
use App\Framework\Utils\Html\UrlRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class UrlRendererTest extends TestCase
{
	#[Group('units')]
	public function testRender(): void
	{
		$field = new UrlField(['id' => 'homepage', 'type' => FieldType::URL, 'name' => 'url', 'value' => 'https://example.com', 'title' => 'homepage']);
		$field->setPattern('https?://.+')->setPlaceholder('https://');

		static::assertSame(
			'<input type="text" name="url" id="homepage" value="https://example.com" title="homepage" pattern="https?://.+" placeholder="https://" aria-describedby="error_homepage">',
			(new UrlRenderer())->render($field)
		);
	}

	#[Group('units')]
	public function testAFieldOfAnotherTypeRendersNothing(): void
	{
		static::assertSame('', (new UrlRenderer())->render(new TextField(['id' => 'a', 'type' => FieldType::TEXT])));
	}
}
