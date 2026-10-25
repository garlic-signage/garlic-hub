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

use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\DropdownRenderer;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\TextField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class DropdownRendererTest extends TestCase
{
	private DropdownRenderer $renderer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->renderer = new DropdownRenderer();
	}

	/**
	 * @param array<string,mixed> $extra
	 */
	private function field(array $extra = []): DropdownField
	{
		return new DropdownField(array_merge(['id' => 'country', 'type' => FieldType::DROPDOWN, 'name' => 'country_name', 'options' => ['de' => 'Germany', 'fr' => 'France']], $extra));
	}

	#[Group('units')]
	public function testRenderWithTheEmptyOption(): void
	{
		static::assertSame(
			'<select id="country" name= "country_name" aria-describedby="error_country"><option value="">-</option><option value="de">Germany</option><option value="fr">France</option></select>',
			$this->renderer->render($this->field())
		);
	}

	#[Group('units')]
	public function testRenderWithoutTheEmptyOption(): void
	{
		static::assertSame(
			'<select id="country" name= "country_name" aria-describedby="error_country"><option value="de">Germany</option><option value="fr">France</option></select>',
			$this->renderer->render($this->field(['options_zero' => false]))
		);
	}

	#[Group('units')]
	public function testTheOptionOfTheValueIsSelected(): void
	{
		$html = $this->renderer->render($this->field(['value' => 'fr']));

		static::assertStringContainsString('<option value="fr" selected>France</option>', $html);
		static::assertStringContainsString('<option value="de">Germany</option>', $html);
	}

	#[Group('units')]
	public function testTheOptionOfTheDefaultValueIsSelected(): void
	{
		$html = $this->renderer->render($this->field(['default_value' => 'de']));

		static::assertStringContainsString('<option value="de" selected>Germany</option>', $html);
	}

	/**
	 * The option with the key 0 (e.g. "off", status 0) is selected when the value is 0.
	 */
	#[Group('units')]
	public function testTheOptionZeroIsSelectedForTheValueZero(): void
	{
		$field = new DropdownField(['id' => 'status', 'type' => FieldType::DROPDOWN, 'options' => [0 => 'Off', 1 => 'On'], 'value' => '0']);

		$html = $this->renderer->render($field);

		static::assertStringContainsString('<option value="0" selected>Off</option>', $html);
		static::assertStringContainsString('<option value="1">On</option>', $html);
	}

	#[Group('units')]
	public function testNoOptionIsSelectedWithoutAValue(): void
	{
		static::assertStringNotContainsString('selected', $this->renderer->render($this->field()));
	}

	#[Group('units')]
	public function testRenderWithoutOptions(): void
	{
		$html = $this->renderer->render($this->field(['options' => [], 'options_zero' => false]));

		static::assertSame('<select id="country" name= "country_name" aria-describedby="error_country"></select>', $html);
	}

	#[Group('units')]
	public function testAFieldOfAnotherTypeRendersNothing(): void
	{
		static::assertSame('', $this->renderer->render(new TextField(['id' => 'a', 'type' => FieldType::TEXT])));
	}
}
