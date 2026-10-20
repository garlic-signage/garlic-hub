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

namespace Tests\Unit\Framework\Core\Config;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Config\ConfigLoaderInterface;
use App\Framework\Exceptions\CoreException;
use Monolog\Level;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private Config $config;
    private ConfigLoaderInterface&MockObject $configLoaderMock;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
		parent::setUp();
		$this->configLoaderMock = $this->createMock(ConfigLoaderInterface::class);
        $this->config           = new Config($this->configLoaderMock, ['key_path' => 'value_path'], ['key_env' => 'value_env']);
    }


    /**
     * @throws CoreException
     */
    #[Group('units')]
    public function testGetConfigValueReturnsValue(): void
    {
        $module = 'test_module';
        $key    = 'test_key';
        $value  = 'test_value';

        $this->configLoaderMock
            ->method('load')
            ->with($module)
            ->willReturn(['heidewitzka' => 'Der Kapitan', 'section' => [$key => $value]]);

        $result = $this->config->getConfigValue($key, $module, 'section');

        static::assertEquals($value, $result);
    }

	#[Group('units')]
	public function testGetEnv(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		static::assertEquals('value_env', $this->config->getEnv('key_env'));
		static::assertEmpty($this->config->getEnv('manamana'));
	}

	#[Group('units')]
	public function testGetEdition(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		$config           = new Config($this->configLoaderMock, [], ['APP_PLATFORM_EDITION' => 'enterprise']);
		static::assertEquals(Config::PLATFORM_EDITION_ENTERPRISE, $config->getEdition());
	}

	#[Group('units')]
	public function testGetEditionDefault(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		static::assertEquals(Config::PLATFORM_EDITION_EDGE, $this->config->getEdition());
	}

	#[Group('units')]
	public function testGetPaths(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		static::assertEquals('value_path', $this->config->getPaths('key_path'));
		static::assertEmpty($this->config->getPaths('manamana'));

	}

		/**
     * @throws CoreException
     */
    #[Group('units')]
    public function testGetConfigValueReturnsNullForNonExistentKey(): void
    {
        $module = 'test_module';
        $key = 'nonexistent_key';

        $this->configLoaderMock
            ->method('load')
            ->with($module)
            ->willReturn(['section' => ['existing_key' => 'value']]);

        $result = $this->config->getConfigValue($key, $module, 'section');

        static::assertEmpty($result);
    }

	#[Group('units')]
	public function testLogLevelIsDebugInDevEnvironment(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		$config = new Config($this->configLoaderMock, [], ['APP_ENV' => 'dev']);
		static::assertEquals(Level::Debug, $config->getLogLevel());
	}

	#[Group('units')]
	public function testLogLevelIsInfoInTestEnvironment(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		$config = new Config($this->configLoaderMock, [], ['APP_ENV' => 'test']);
		static::assertEquals(Level::Info, $config->getLogLevel());
	}

	#[Group('units')]
	public function testLogLevelIsErrorInProdEnvironment(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		$config = new Config($this->configLoaderMock, [], ['APP_ENV' => 'prod']);
		static::assertEquals(Level::Error, $config->getLogLevel());
	}

	#[Group('units')]
	public function testLogLevelIsWarningInUnknownEnvironment(): void
	{
		$this->configLoaderMock->expects($this->never())->method($this->anything());
		$config = new Config($this->configLoaderMock, [], ['APP_ENV' => 'unknown']);
		static::assertEquals(Level::Info, $config->getLogLevel());
	}

	#[Group('units')]
	public function logLevelIsWarningWhenEnvIsNotSet(): void
	{
		$config = new Config($this->configLoaderMock, [], []);
		static::assertEquals(Level::Warning, $config->getLogLevel());
	}

	/**
     * @throws CoreException
     */
    #[Group('units')]
    public function testGetFullConfigDataByModule(): void
    {
        $module = 'test_module';
        $configData = ['key1' => 'value1', 'key2' => 'value2'];

        $this->configLoaderMock
            ->method('load')
            ->with($module)
            ->willReturn($configData);

        $result = $this->config->getFullConfigDataByModule($module);

        static::assertEquals($configData, $result);
    }

    /**
     * @throws CoreException
     */
    #[Group('units')]
    public function testPreloadModulesCachesConfigurations(): void
    {
        $modules = ['module1', 'module2'];
        $configData = [
            'module1' => ['key1' => 'value1'],
            'module2' => ['key2' => 'value2'],
        ];

        $this->configLoaderMock
            ->expects($this->atLeastOnce())->method('load')
            ->willReturnCallback(function ($module) use ($configData) {
                return $configData[$module] ?? [];
            });

        // Preload Module
        $this->config->preloadModules($modules);

        // check if configuration loaded correctly
        static::assertEquals($configData['module1'], $this->config->getFullConfigDataByModule('module1'));
        static::assertEquals($configData['module2'], $this->config->getFullConfigDataByModule('module2'));
    }

    /**
     * @throws CoreException
     */
    #[Group('units')]
    public function testGetConfigForModuleCachesResults(): void
    {
        $module = 'test_module';
        $configData = ['key1' => 'value1'];

        $this->configLoaderMock
            ->expects($this->once())
            ->method('load')
            ->with($module)
            ->willReturn($configData);

        // request from loader
        $result1 = $this->config->getFullConfigDataByModule($module);

        // 2nd request from cache
        $result2 = $this->config->getFullConfigDataByModule($module);

        static::assertEquals($configData, $result1);
        static::assertEquals($configData, $result2);
    }

    #[Group('units')]
    public function testGetConfigForModuleThrowsExceptionIfLoaderFails(): void
    {
        $module = 'test_module';

        $this->configLoaderMock
            ->method('load')
            ->with($module)
            ->willThrowException(new CoreException('Error loading module'));

        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Error loading module');

        $this->config->getFullConfigDataByModule($module);
    }
}
