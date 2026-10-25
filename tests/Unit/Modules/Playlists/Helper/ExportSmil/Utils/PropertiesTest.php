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

namespace Tests\Unit\Modules\Playlists\Helper\ExportSmil\Utils;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PropertiesTest extends TestCase
{
	private Config&Stub $configStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub = $this->createStub(Config::class);
		// the defaults of the configuration, which must not win against a value of the item
		$this->configStub->method('getConfigValue')->willReturnMap([
			['fit', 'playlists', 'Defaults', 'meetBest'],
			['media_align', 'playlists', 'Defaults', 'center'],
			['volume', 'playlists', 'Defaults', '100']
		]);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testDefaultValues(): void
	{
		$properties = new Properties($this->configStub, []);

		static::assertSame('fit="meetBest" ', $properties->getFit());
		static::assertSame('mediaAlign="center" ', $properties->getMediaAlign());
		static::assertSame('soundLevel="100%" ', $properties->getVolume());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testParameterValues(): void
	{
		$properties = new Properties($this->configStub, ['fit' => 'slice', 'media_align' => 'midRight', 'volume' => '56']);

		static::assertSame('fit="slice" ', $properties->getFit());
		static::assertSame('mediaAlign="midRight" ', $properties->getMediaAlign());
		static::assertSame('soundLevel="56%" ', $properties->getVolume());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testNonsenseValues(): void
	{
		$properties = new Properties($this->configStub, ['fit' => 'Bämm', 'media_align' => 'Bämm']);

		static::assertEmpty($properties->getFit());
		static::assertEmpty($properties->getMediaAlign());
		static::assertSame('soundLevel="100%" ', $properties->getVolume());
	}

}
