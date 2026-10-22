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

        static::assertEquals('5 items', $formatter->format(['count' => 5]));
    }

    #[Group('units')]
    public function testCreateThrowsFrameworkExceptionOnInvalidPattern(): void
    {
        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('MessageFormatter instantiation error');

        $this->factory->create('en_US', '{count items'); // Missing closing brace
    }
}
