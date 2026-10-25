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
use App\Framework\Utils\Html\FieldType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ClipboardTextFieldTest extends TestCase
{
	private ClipboardTextField $field;

	protected function setUp(): void
	{
		parent::setUp();
		$this->field = new ClipboardTextField(['id' => 'link', 'type' => FieldType::CLIPBOARD_TEXT]);
	}

	#[Group('units')]
	public function testTheTitlesAreEmptyByDefault(): void
	{
		static::assertSame('', $this->field->getDeleteTitle());
		static::assertSame('', $this->field->getRefreshTitle());
	}

	#[Group('units')]
	public function testSetTheTitles(): void
	{
		$result = $this->field->setDeleteTitle('Delete link')->setRefreshTitle('Renew link');

		static::assertSame($this->field, $result);
		static::assertSame('Delete link', $this->field->getDeleteTitle());
		static::assertSame('Renew link', $this->field->getRefreshTitle());
	}
}
