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

use App\Framework\Utils\Html\CsrfTokenField;
use App\Framework\Utils\Html\CsrfTokenRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class CsrfTokenRendererTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithBasicAttributes(): void
	{
		$fieldStub = $this->createStub(CsrfTokenField::class);
		$fieldStub->method('getName')->willReturn('csrf_token');
		$fieldStub->method('getId')->willReturn('csrf_token');
		$fieldStub->method('getValue')->willReturn('the_token_in_some_hash');

		$renderer = new CsrfTokenRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<input type="hidden" name="csrf_token" id="csrf_token" value="the_token_in_some_hash">';
		static::assertSame($expected, $result);
	}
}
