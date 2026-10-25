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
use App\Framework\Utils\Html\NumberField;
use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\TextRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TextInputRendererTest extends TestCase
{
	private TextRenderer $renderer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->renderer = new TextRenderer();
	}

	#[Group('units')]
	public function testRenderWithBasicAttributes(): void
	{
		$field = new TextField(['id' => 'user_1', 'type' => FieldType::TEXT, 'name' => 'username', 'title' => 'edit username', 'value' => 'JohnDoe']);

		static::assertSame(
			'<input type="text" name="username" id="user_1" value="JohnDoe" title="edit username" aria-describedby="error_user_1">',
			$this->renderer->render($field)
		);
	}

	#[Group('units')]
	public function testTheNameFallsBackToTheIdAndNothingOptionalIsRenderedWhenEmpty(): void
	{
		$field = new TextField(['id' => 'user_1', 'type' => FieldType::TEXT]);

		static::assertSame('<input type="text" name="user_1" id="user_1" value="" aria-describedby="error_user_1">', $this->renderer->render($field));
	}

	#[Group('units')]
	public function testRenderWithLabel(): void
	{
		$field = new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'label' => 'Name']);

		static::assertSame('<input type="text" name="a" id="a" value="" label="Name" aria-describedby="error_a">', $this->renderer->render($field));
	}

	#[Group('units')]
	public function testRenderWithValidationRules(): void
	{
		$field = new TextField([
			'id' => 'email_input', 'type' => FieldType::TEXT, 'name' => 'email', 'title' => 'edit email', 'value' => 'john@example.com',
			'rules' => ['required' => true, 'maxlength' => 255],
		]);

		static::assertSame(
			'<input type="text" name="email" id="email_input" value="john@example.com" title="edit email" required="required" maxlength="255" aria-describedby="error_email_input">',
			$this->renderer->render($field)
		);
	}

	#[Group('units')]
	public function testRenderWithAdditionalAttributes(): void
	{
		$field = new TextField([
			'id' => 'user_1', 'type' => FieldType::TEXT, 'name' => 'username', 'value' => 'JohnDoe',
			'attributes' => ['class' => 'form-control', 'placeholder' => 'Enter your username'],
		]);

		static::assertSame(
			'<input type="text" name="username" id="user_1" value="JohnDoe" class="form-control" placeholder="Enter your username" aria-describedby="error_user_1">',
			$this->renderer->render($field)
		);
	}

	#[Group('units')]
	public function testRenderWithAllOptions(): void
	{
		$field = new TextField([
			'id' => 'password_input', 'type' => FieldType::TEXT, 'name' => 'password', 'title' => 'edit password',
			'attributes' => ['class' => 'password-input', 'placeholder' => 'Enter your password'],
			'rules'      => ['required' => true, 'maxlength' => 20],
		]);

		static::assertSame(
			'<input type="text" name="password" id="password_input" value="" title="edit password" class="password-input" placeholder="Enter your password" required="required" maxlength="20" aria-describedby="error_password_input">',
			$this->renderer->render($field)
		);
	}

	#[Group('units')]
	public function testTheDefaultValueIsRenderedForAnEmptyValue(): void
	{
		$field = new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'default_value' => 'guest']);

		static::assertStringContainsString('value="guest"', $this->renderer->render($field));
	}

	/**
	 * A zero is a value: it must not be replaced by the default.
	 */
	#[Group('units')]
	public function testZeroIsRendered(): void
	{
		$field = new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'value' => '0', 'default_value' => 'x']);

		static::assertStringContainsString('value="0"', $this->renderer->render($field));
	}

	/**
	 * The renderer can be used for several fields one after the other without keeping the attributes of the one before.
	 */
	#[Group('units')]
	public function testTheRendererForgetsTheFieldBefore(): void
	{
		$this->renderer->render(new TextField(['id' => 'first', 'type' => FieldType::TEXT, 'attributes' => ['class' => 'one'], 'rules' => ['required' => true]]));

		$html = $this->renderer->render(new TextField(['id' => 'second', 'type' => FieldType::TEXT]));

		static::assertSame('<input type="text" name="second" id="second" value="" aria-describedby="error_second">', $html);
	}

	#[Group('units')]
	public function testTheAttributesOfTheFieldAreRenderedWhateverTheRendererIs(): void
	{
		$number = new NumberField(['id' => 'n', 'type' => FieldType::NUMBER, 'min' => 1, 'max' => 3]);

		static::assertSame('<input type="text" name="n" id="n" value="" min="1" max="3" aria-describedby="error_n">', $this->renderer->render($number));
	}
}
