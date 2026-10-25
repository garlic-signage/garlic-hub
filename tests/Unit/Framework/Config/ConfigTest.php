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

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

class ConfigTest extends TestCase
{
	private Config $config;
	private RecordingConfigLoader $loader;

	protected function setUp(): void
	{
		parent::setUp();

		$this->loader = new RecordingConfigLoader([
			'module_a' => ['top_key' => 'top_a', 'section' => ['key' => 'value_a']],
			'module_b' => ['section' => ['key' => 'value_b']],
		]);
		$this->config = new Config($this->loader, ['key_path' => 'value_path'], ['key_env' => 'value_env']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testGetConfigValueReturnsValueOfSection(): void
	{
		static::assertSame('value_a', $this->config->getConfigValue('key', 'module_a', 'section'));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testGetConfigValueKeepsModulesApart(): void
	{
		static::assertSame('value_b', $this->config->getConfigValue('key', 'module_b', 'section'));
		static::assertSame('value_a', $this->config->getConfigValue('key', 'module_a', 'section'));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testGetConfigValueWithoutSectionReadsTopLevel(): void
	{
		static::assertSame('top_a', $this->config->getConfigValue('top_key', 'module_a'));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testGetConfigValueFallsBackToTopLevelWhenSectionDoesNotExist(): void
	{
		static::assertSame('top_a', $this->config->getConfigValue('top_key', 'module_a', 'missing_section'));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testGetConfigValueReturnsEmptyStringForMissingKey(): void
	{
		static::assertSame('', $this->config->getConfigValue('missing_key', 'module_a', 'section'));
		static::assertSame('', $this->config->getConfigValue('missing_key', 'module_a'));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testGetFullConfigDataByModule(): void
	{
		static::assertSame(
			['top_key' => 'top_a', 'section' => ['key' => 'value_a']],
			$this->config->getFullConfigDataByModule('module_a')
		);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testModuleIsLoadedOnlyOnce(): void
	{
		$this->config->getFullConfigDataByModule('module_a');
		$this->config->getConfigValue('key', 'module_a', 'section');
		$this->config->getFullConfigDataByModule('module_a');

		static::assertSame(['module_a'], $this->loader->loaded);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testPreloadModulesLoadsEachModuleOnceAndServesThemFromCache(): void
	{
		$this->config->preloadModules(['module_a', 'module_b']);
		$a = $this->config->getFullConfigDataByModule('module_a');
		$b = $this->config->getFullConfigDataByModule('module_b');

		static::assertSame(['module_a', 'module_b'], $this->loader->loaded);
		static::assertSame('value_a', $a['section']['key']);
		static::assertSame('value_b', $b['section']['key']);
	}

	#[Group('units')]
	public function testFailingLoaderThrowsException(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Unable to access configuration file for unknown');

		$this->config->getFullConfigDataByModule('unknown');
	}

	#[Group('units')]
	public function testGetEnv(): void
	{
		static::assertSame('value_env', $this->config->getEnv('key_env'));
		static::assertSame('', $this->config->getEnv('unknown'));
	}

	#[Group('units')]
	public function testGetPaths(): void
	{
		static::assertSame('value_path', $this->config->getPaths('key_path'));
		static::assertSame('', $this->config->getPaths('unknown'));
	}

	#[Group('units')]
	public function testGetEdition(): void
	{
		$config = new Config($this->loader, [], ['APP_PLATFORM_EDITION' => 'enterprise']);

		static::assertSame(Config::PLATFORM_EDITION_ENTERPRISE, $config->getEdition());
	}

	#[Group('units')]
	public function testGetEditionDefaultsToEdge(): void
	{
		static::assertSame(Config::PLATFORM_EDITION_EDGE, $this->config->getEdition());
	}

	/**
	 * @param array<string,string> $env
	 */
	#[Group('units')]
	#[DataProvider('logLevelProvider')]
	public function testGetLogLevel(array $env, Level $expected): void
	{
		$config = new Config($this->loader, [], $env);

		static::assertSame($expected, $config->getLogLevel());
	}

	/**
	 * @return array<string, array{array<string,string>, Level}>
	 */
	public static function logLevelProvider(): array
	{
		return [
			'dev'         => [['APP_ENV' => 'dev'], Level::Debug],
			'test'        => [['APP_ENV' => 'test'], Level::Info],
			'prod'        => [['APP_ENV' => 'prod'], Level::Error],
			'unknown env' => [['APP_ENV' => 'unknown'], Level::Info],
			'env not set' => [[], Level::Info],
		];
	}
}
