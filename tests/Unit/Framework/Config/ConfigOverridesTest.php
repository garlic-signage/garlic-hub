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

namespace Tests\Unit\Framework\Config;

use App\Framework\Config\ConfigOverrides;
use App\Framework\Config\OverridingConfigLoader;
use App\Framework\Exceptions\CoreException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * The values an admin changed, in var/settings, over the defaults of config/settings.
 */
class ConfigOverridesTest extends TestCase
{
	private string $directory;
	private ConfigOverrides $overrides;

	protected function setUp(): void
	{
		parent::setUp();
		$this->directory = sys_get_temp_dir() . '/garlic-hub-overrides-' . bin2hex(random_bytes(4));
		$this->overrides = new ConfigOverrides($this->directory);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->directory . '/*') ?: [] as $file)
			unlink($file);
		if (is_dir($this->directory))
			rmdir($this->directory);
		parent::tearDown();
	}

	#[Group('units')]
	public function testWithoutAFileThereAreNoChanges(): void
	{
		static::assertSame([], $this->overrides->load('system'));
	}

	#[Group('units')]
	public function testTheChangesAreWrittenAndReadAgain(): void
	{
		$this->overrides->save('system', ['Audit' => ['enabled' => '1', 'retention_days' => '30'], 'Queue' => []]);

		static::assertSame(['Audit' => ['enabled' => '1', 'retention_days' => '30']], $this->overrides->load('system'));
		static::assertFileExists($this->directory . '/config_system.ini');
		static::assertSame([$this->directory . '/config_system.ini'], glob($this->directory . '/*'), 'No temporary file is left.');
	}

	#[Group('units')]
	public function testWithoutChangesTheFileIsRemoved(): void
	{
		$this->overrides->save('system', ['Audit' => ['enabled' => '1']]);

		$this->overrides->save('system', ['Audit' => []]);

		static::assertFileDoesNotExist($this->directory . '/config_system.ini');
	}

	#[Group('units')]
	public function testAQuoteWouldEndTheValueAndIsRefused(): void
	{
		$this->expectException(CoreException::class);
		$this->overrides->save('system', ['Audit' => ['enabled' => 'a"b']]);
	}

	#[Group('units')]
	public function testAnOverrideChangesOnlyKeysOfTheDefaults(): void
	{
		$defaults = new RecordingConfigLoader(['system' => ['Audit' => ['enabled' => '0', 'retention_days' => '365'], 'Queue' => ['enabled' => '0']]]);
		$this->overrides->save('system', ['Audit' => ['enabled' => '1', 'unknown' => 'x'], 'Other' => ['key' => 'y']]);

		$config = new OverridingConfigLoader($defaults, $this->overrides)->load('system');

		static::assertSame(['Audit' => ['enabled' => '1', 'retention_days' => '365'], 'Queue' => ['enabled' => '0']], $config);
	}
}
