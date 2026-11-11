<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\System;

use App\Framework\System\AppVersion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class AppVersionTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/garlic-version-' . bin2hex(random_bytes(4));
		mkdir($this->dir);
	}

	protected function tearDown(): void
	{
		foreach (['VERSION', 'VERSION_BUILD'] as $file)
		{
			if (is_file($this->dir . '/' . $file))
				unlink($this->dir . '/' . $file);
		}
		rmdir($this->dir);
		parent::tearDown();
	}

	#[Group('units')]
	public function testVersionWithBuildNumber(): void
	{
		file_put_contents($this->dir . '/VERSION', "1.3.0-dev\n");
		file_put_contents($this->dir . '/VERSION_BUILD', "1234\n");

		static::assertSame('1.3.0-dev (Build: 1234)', new AppVersion($this->dir)->getVersion());
	}

	#[Group('units')]
	public function testVersionWithoutBuildFile(): void
	{
		file_put_contents($this->dir . '/VERSION', "1.3.0-dev\n");

		static::assertSame('1.3.0-dev', new AppVersion($this->dir)->getVersion());
	}

	#[Group('units')]
	public function testBuildNumberWhichIsNoNumberIsIgnored(): void
	{
		file_put_contents($this->dir . '/VERSION', '1.3.0');
		file_put_contents($this->dir . '/VERSION_BUILD', "fatal: not a git repository\n");

		static::assertSame('1.3.0', new AppVersion($this->dir)->getVersion());
	}

	#[Group('units')]
	public function testEmptyBuildFileIsIgnored(): void
	{
		file_put_contents($this->dir . '/VERSION', '1.3.0');
		file_put_contents($this->dir . '/VERSION_BUILD', '');

		static::assertSame('1.3.0', new AppVersion($this->dir)->getVersion());
	}

	#[Group('units')]
	public function testWithoutVersionFileNothingIsReturned(): void
	{
		file_put_contents($this->dir . '/VERSION_BUILD', '1234');

		static::assertSame('', new AppVersion($this->dir)->getVersion());
	}

	#[Group('units')]
	public function testOnlyTheFirstLineCounts(): void
	{
		file_put_contents($this->dir . '/VERSION', "  1.3.0-dev  \nsomething else\n");

		static::assertSame('1.3.0-dev', new AppVersion($this->dir)->getVersion());
	}
}
