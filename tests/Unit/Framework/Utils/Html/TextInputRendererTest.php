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

use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\TextRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class TextInputRendererTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithBasicAttributes(): void
	{
		$fieldStub = $this->createStub(TextField::class);
		$fieldStub->method('getName')->willReturn('username');
		$fieldStub->method('getTitle')->willReturn('edit username');
		$fieldStub->method('getId')->willReturn('user_1');
		$fieldStub->method('getValue')->willReturn('JohnDoe');
		$fieldStub->method('getValidationRules')->willReturn([]);
		$fieldStub->method('getAttributes')->willReturn([]);

		$renderer = new TextRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<input type="text" name="username" id="user_1" value="JohnDoe" title="edit username" aria-describedby="error_user_1">';
		static::assertSame($expected, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithValidationRules(): void
	{
		$fieldStub = $this->createStub(TextField::class);
		$fieldStub->method('getName')->willReturn('email');
		$fieldStub->method('getTitle')->willReturn('edit email');
		$fieldStub->method('getId')->willReturn('email_input');
		$fieldStub->method('getValue')->willReturn('john@example.com');
		$fieldStub->method('getValidationRules')->willReturn([
			'required' => true,
			'maxlength' => 255
		]);
		$fieldStub->method('getAttributes')->willReturn([]);

		$renderer = new TextRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<input type="text" name="email" id="email_input" value="john@example.com" title="edit email" required="required" maxlength="255" aria-describedby="error_email_input">';
		static::assertSame($expected, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithAdditionalAttributes(): void
	{
		$fieldStub = $this->createStub(TextField::class);
		$fieldStub->method('getName')->willReturn('username');
		$fieldStub->method('getTitle')->willReturn('edit username');
		$fieldStub->method('getId')->willReturn('user_1');
		$fieldStub->method('getValue')->willReturn('JohnDoe');
		$fieldStub->method('getValidationRules')->willReturn([]);
		$fieldStub->method('getAttributes')->willReturn([
			'class' => 'form-control',
			'placeholder' => 'Enter your username'
		]);

		$renderer = new TextRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<input type="text" name="username" id="user_1" value="JohnDoe" title="edit username" class="form-control" placeholder="Enter your username" aria-describedby="error_user_1">';
		static::assertSame($expected, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithAllOptions(): void
	{
		$fieldStub = $this->createStub(TextField::class);
		$fieldStub->method('getName')->willReturn('password');
		$fieldStub->method('getTitle')->willReturn('edit password');
		$fieldStub->method('getId')->willReturn('password_input');
		$fieldStub->method('getValue')->willReturn('');
		$fieldStub->method('getValidationRules')->willReturn([
			'required' => true,
			'maxlength' => 20
		]);
		$fieldStub->method('getAttributes')->willReturn([
			'class' => 'password-input',
			'placeholder' => 'Enter your password'
		]);

		$renderer = new TextRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<input type="text" name="password" id="password_input" value="" title="edit password" class="password-input" placeholder="Enter your password" required="required" maxlength="20" aria-describedby="error_password_input">';
		static::assertSame($expected, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderEscapesSpecialCharacters(): void
	{
		$fieldStub = $this->createStub(TextField::class);
		$fieldStub->method('getName')->willReturn('name');
		$fieldStub->method('getTitle')->willReturn('say "hi"');
		$fieldStub->method('getId')->willReturn('id_1');
		$fieldStub->method('getValue')->willReturn('"><script>alert(\'x\')</script> & Co');
		$fieldStub->method('getValidationRules')->willReturn([]);
		$fieldStub->method('getAttributes')->willReturn([]);

		$renderer = new TextRenderer();
		$result = $renderer->render($fieldStub);

		$expected = '<input type="text" name="name" id="id_1" value="&quot;&gt;&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt; &amp; Co" title="say &quot;hi&quot;" aria-describedby="error_id_1">';
		static::assertSame($expected, $result);
	}
}
