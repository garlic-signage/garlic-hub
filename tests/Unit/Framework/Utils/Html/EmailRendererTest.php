<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\EmailRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class EmailRendererTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithMinimumAttributes(): void
	{
		$fieldStub = $this->createStub(EmailField::class);
		$fieldStub->method('getName')->willReturn('email_1');
		$fieldStub->method('getTitle')->willReturn('edit email_1');
		$fieldStub->method('getId')->willReturn('email_1');
		$fieldStub->method('getValue')->willReturn('test@test.kl');
		$fieldStub->method('getValidationRules')->willReturn([]);
		$fieldStub->method('getAttributes')->willReturn([]);

		$renderer = new EmailRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<input type="email" name="email_1" id="email_1" value="test@test.kl" title="edit email_1" aria-describedby="error_email_1">';
		static::assertSame($expected, $result);
	}
}
