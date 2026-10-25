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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class NumberFieldTest extends TestCase
{
	/**
	 * @param array<string,mixed> $extra
	 * @param array<string,mixed> $expected
	 */
	#[Group('units')]
	#[DataProvider('limitProvider')]
	public function testMinAndMaxBecomeAttributes(array $extra, array $expected): void
	{
		$field = new NumberField(['id' => 'logins', 'type' => FieldType::NUMBER, 'name' => 'user_logins'] + $extra);

		static::assertSame($expected, $field->getAttributes());
	}

	/**
	 * @return array<string, array{array<string,mixed>, array<string,mixed>}>
	 */
	public static function limitProvider(): array
	{
		return [
			'none'       => [[], []],
			'min'        => [['min' => 5], ['min' => 5]],
			'max'        => [['max' => 15], ['max' => 15]],
			'both'       => [['min' => 5, 'max' => 15], ['min' => 5, 'max' => 15]],
			'zero limits' => [['min' => 0, 'max' => 0], ['min' => 0, 'max' => 0]],
		];
	}

	#[Group('units')]
	public function testTheLimitsAreAddedToTheGivenAttributes(): void
	{
		$field = new NumberField(['id' => 'a', 'type' => FieldType::NUMBER, 'min' => 1, 'attributes' => ['step' => 5]]);

		static::assertSame(['step' => 5, 'min' => 1], $field->getAttributes());
	}
}
