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

namespace Tests\Unit\Framework\Localization\Translate;

use App\Framework\Localization\Translate\MessageFormatterFactory;
use App\Framework\Exceptions\FrameworkException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MessageFormatterFactoryTest extends TestCase
{
    private MessageFormatterFactory $factory;

    protected function setUp(): void
    {
		parent::setUp();
		$this->factory = new MessageFormatterFactory();
    }

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
    public function testCreateReturnsMessageFormatter(): void
    {
        $locale = 'en_US';
        $pattern = '{count} items';

        $formatter = $this->factory->create($locale, $pattern);

        static::assertSame('5 items', $formatter->format(['count' => 5]));
    }

    #[Group('units')]
    public function testCreateThrowsFrameworkExceptionOnInvalidPattern(): void
    {
        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('MessageFormatter instantiation error');

        $this->factory->create('en_US', '{count items'); // Missing closing brace
    }

	/**
	 * The plural rules are the reason for MessageFormatter: they differ per language.
	 *
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('pluralProvider')]
	public function testFormatAppliesThePluralRulesOfTheLanguage(string $locale, int $count, string $expected): void
	{
		$pattern = $locale === 'ru'
			? '{count, plural, one{# файл} few{# файла} many{# файлов} other{# файла}}'
			: '{count, plural, one{# item} other{# items}}';

		static::assertSame($expected, $this->factory->create($locale, $pattern)->format(['count' => $count]));
	}

	/**
	 * @return array<string, array{string, int, string}>
	 */
	public static function pluralProvider(): array
	{
		return [
			'en one'    => ['en', 1, '1 item'],
			'en other'  => ['en', 5, '5 items'],
			'en zero'   => ['en', 0, '0 items'],
			'ru one'    => ['ru', 1, '1 файл'],
			'ru few'    => ['ru', 3, '3 файла'],
			'ru many'   => ['ru', 5, '5 файлов'],
			'ru 21'     => ['ru', 21, '21 файл'],
		];
	}
}
