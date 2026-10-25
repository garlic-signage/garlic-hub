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

use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\ClipboardTextRenderer;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\TextField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ClipboardTextRendererTest extends TestCase
{
	#[Group('units')]
	public function testRender(): void
	{
		$field = new ClipboardTextField(['id' => 'link', 'type' => FieldType::CLIPBOARD_TEXT, 'title' => 'Copy', 'value' => 'https://example.com/v/abc']);
		$field->setDeleteTitle('Delete')->setRefreshTitle('Renew');

		$expected = '<input type="text" id="link" class="verification-link" title="Copy" value="https://example.com/v/abc" readonly>
<button type="button" data-id="link" class="copy-verification-link" title="Copy."><i class="bi bi-clipboard"></i></button>
<button type="button" data-id="link" class="delete-verification-link" title="Delete."><i class="bi bi-trash"></i></button>
<button type="button" data-id="link" class="refresh-verification-link" title="Renew."><i class="bi bi-arrow-clockwise"></i></button>
';

		static::assertSame($expected, (new ClipboardTextRenderer())->render($field));
	}

	#[Group('units')]
	public function testAFieldOfAnotherTypeRendersNothing(): void
	{
		static::assertSame('', (new ClipboardTextRenderer())->render(new TextField(['id' => 'a', 'type' => FieldType::TEXT])));
	}
}
