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

namespace Tests\Integration\Framework\Config;

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Exceptions\CoreException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Config with the real IniConfigLoader on real ini files: what the application gets from a config file.
 */
#[Group('io')]
class ConfigTest extends TestCase
{
	private Config $config;

	protected function setUp(): void
	{
		parent::setUp();

		$directory   = __DIR__ . '/../../../resources/config_tests';
		$this->config = new Config(new IniConfigLoader($directory));
	}

	/**
	 * @throws CoreException
	 */
	public function testValuesOfASectionComeFromTheFile(): void
	{
		static::assertSame('Lobby', $this->config->getConfigValue('name', 'sections', 'player'));
		static::assertSame('Other', $this->config->getConfigValue('name', 'sections', 'other'));
	}

	/**
	 * @throws CoreException
	 */
	public function testValuesAreRawStrings(): void
	{
		static::assertSame('true', $this->config->getConfigValue('enabled', 'sections', 'player'));
		static::assertSame('10', $this->config->getConfigValue('limit', 'sections', 'player'));
	}

	/**
	 * @throws CoreException
	 */
	public function testTopLevelValueWithoutSection(): void
	{
		static::assertSame('top', $this->config->getConfigValue('top_key', 'sections'));
		static::assertSame('core', $this->config->getConfigValue('edition', 'valid'));
	}

	/**
	 * @throws CoreException
	 */
	public function testPreloadLoadsSeveralModules(): void
	{
		$this->config->preloadModules(['valid', 'sections']);

		static::assertSame(['edition' => 'core'], $this->config->getFullConfigDataByModule('valid'));
		static::assertSame('top', $this->config->getFullConfigDataByModule('sections')['top_key']);
	}

	public function testMissingModuleFileThrows(): void
	{
		$this->expectException(CoreException::class);

		$this->config->getFullConfigDataByModule('nonexistent');
	}

	public function testPreloadOfUnparsableModuleThrows(): void
	{
		$this->expectException(CoreException::class);

		$this->config->preloadModules(['valid', 'invalid']);
	}
}
