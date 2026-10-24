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

namespace Tests\Unit\Framework\Config;

use App\Framework\Config\IniConfigLoader;
use App\Framework\Exceptions\CoreException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class IniConfigLoaderTest extends TestCase
{
	protected string $baseDirectory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->baseDirectory = getenv('TEST_BASE_DIR') . '/resources/config_tests/';
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadForValidFile(): void
	{
		$loader = new IniConfigLoader($this->baseDirectory);

		static::assertSame(['edition' => 'core'], $loader->load('valid'));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadKeepsSectionsAndRawValues(): void
	{
		$loader = new IniConfigLoader($this->baseDirectory);

		static::assertSame(
			[
				'top_key' => 'top',
				'player'  => ['enabled' => 'true', 'limit' => '10', 'name' => 'Lobby'],
				'other'   => ['name' => 'Other'],
			],
			$loader->load('sections')
		);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testLoadWorksWithAndWithoutTrailingSlashInPath(): void
	{
		$withSlash    = new IniConfigLoader(rtrim($this->baseDirectory, '/') . '/');
		$withoutSlash = new IniConfigLoader(rtrim($this->baseDirectory, '/'));

		static::assertSame(['edition' => 'core'], $withSlash->load('valid'));
		static::assertSame(['edition' => 'core'], $withoutSlash->load('valid'));
	}

	#[Group('units')]
	public function testLoadThrowsExceptionForMissingFile(): void
	{
		$loader = new IniConfigLoader($this->baseDirectory);

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Unable to access configuration file: ' . $this->baseDirectory . 'config_nonexistent.ini');

		$loader->load('nonexistent');
	}

	#[Group('units')]
	public function testLoadThrowsExceptionForUnparsableFile(): void
	{
		$loader = new IniConfigLoader($this->baseDirectory);

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Error parsing configuration file: ' . $this->baseDirectory . 'config_invalid.ini');

		$loader->load('invalid');
	}
}
