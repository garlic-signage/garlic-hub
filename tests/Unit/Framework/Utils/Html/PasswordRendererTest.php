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

use App\Framework\Utils\Html\PasswordField;
use App\Framework\Utils\Html\PasswordRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class PasswordRendererTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithMinimumAttributes(): void
	{
		$fieldStub = $this->createStub(PasswordField::class);
		$fieldStub->method('getName')->willReturn('password');
		$fieldStub->method('getTitle')->willReturn('edit password');
		$fieldStub->method('getId')->willReturn('password_1');
		$fieldStub->method('getValue')->willReturn('janzjeheim');
		$fieldStub->method('getValidationRules')->willReturn([]);
		$fieldStub->method('getAttributes')->willReturn([]);

		$renderer = new PasswordRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<div class="password-container"><input type="password" name="password" id="password_1" value="janzjeheim" title="edit password" aria-describedby="error_password_1"><span class="toggle-password bi bi-eye-fill" id="toggle_password_1"></span></div>';
		static::assertSame($expected, $result);
	}
}
