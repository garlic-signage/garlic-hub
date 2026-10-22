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

use App\Framework\Localization\Translate\IniTranslationLoader;
use App\Framework\Exceptions\CoreException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class IniTranslationLoaderTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
		parent::setUp();
		$this->baseDirectory = getenv('TEST_BASE_DIR') . '/resources/translations_tests/';
    }

    /**
     * @throws CoreException
     */
    #[Group('units')]
    public function testLoadForValidFile(): void
    {
        $loader = new IniTranslationLoader($this->baseDirectory);
        $result = $loader->load('en', 'valid');
        static::assertArrayHasKey('username', $result);
    }

    #[Group('units')]
    public function testLoadThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Translation file not found');
        $loader = new IniTranslationLoader($this->baseDirectory);
        $loader->load('en', 'nonexistent');
    }

    #[Group('units')]
    public function testLoadThrowsExceptionForInvalidIniFile(): void
    {
        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Invalid INI file format');
        $loader = new IniTranslationLoader($this->baseDirectory);
        $loader->load('en', 'invalid');
    }

}
