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

namespace Tests\Unit\Modules\Playlists\Helper\ExportSmil\items;

use App\Framework\Core\Config\Config;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use App\Modules\Playlists\Helper\ExportSmil\items\Media;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use App\Modules\Playlists\Helper\ItemFlags;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConcreteMedia extends Media
{
	public function createSmilTag(): string
	{
		return 'mediaTag';
	}

	public function testCollectAttributes(): string
	{
		return $this->collectMediaAttributes();
	}

	public function testCollectParameters(): string
	{
		return $this->collectParameters();
	}

	public function testLoggable(): string
	{
		return $this->checkLoggable();
	}
}

class MediaTest extends TestCase
{
	private Config&Stub $configStub;
	private Trigger&Stub $beginStub;
	private Trigger&Stub $endStub;
	private Conditional&Stub $conditionalStub;
	private Properties&Stub $propertiesStub;
	private ConcreteMedia $concreteMedia;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub = $this->createStub(Config::class);
		$this->propertiesStub = $this->createStub(Properties::class);
		$this->beginStub = $this->createStub(Trigger::class);
		$this->endStub = $this->createStub(Trigger::class);
		$this->conditionalStub = $this->createStub(Conditional::class);

	}

	#[Group('units')]
	public function testGetPrefetchTag(): void
	{
		$item = ['item_id' => 1, 'item_name' => 'Example Title', 'item_duration' => 1000, 'mimetype' => 'video/mp4'];
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		$this->concreteMedia->setLink('/example/path');

		$result = $this->concreteMedia->getPrefetchTag();
		static::assertSame(Base::TABSTOPS_TAG . '<prefetch src="example/path" />' . "\n", $result);
	}

	#[Group('units')]
	public function testGetPrefetchTagWithWebsite(): void
	{
		$item = ['item_id' => 1, 'item_name' => 'Example Title', 'item_duration' => 1000, 'mimetype' => 'text/html'];
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		$this->concreteMedia->setLink('/example/path');

		static::assertEmpty($this->concreteMedia->getPrefetchTag());
	}

	#[Group('units')]
	public function testCollectMediaAttributes(): void
	{
		$item = ['item_id' => 1, 'item_name' => 'Sample Item', 'item_duration' => 5000];
		$this->propertiesStub->method('getFit')->willReturn('fit="fill" ');
		$this->propertiesStub->method('getMediaAlign')->willReturn('align="center" ');
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		$this->concreteMedia->setLink('/test/path');

		$result = $this->concreteMedia->testCollectAttributes();

		static::assertSame('title="Sample Item" region="screen" src="test/path" dur="5000s" fit="fill" align="center" ', $result);
	}

	#[Group('units')]
	public function testCollectMediaAttributesEmptyProperties(): void
	{
		$item = ['item_id' => 1, 'item_name' => 'Sample Item', 'item_duration' => 5000];
		$this->propertiesStub->method('getFit')->willReturn('');
		$this->propertiesStub->method('getMediaAlign')->willReturn('');
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		$this->concreteMedia->setLink('/empty/path');

		$result = $this->concreteMedia->testCollectAttributes();

		static::assertSame('title="Sample Item" region="screen" src="empty/path" dur="5000s" ', $result);
	}

	#[Group('units')]
	public function testCollectParametersWithFileDatasource(): void
	{
		$item = ['item_id' => 1, 'datasource' => 'file', 'flags' => 0];
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		$result = $this->concreteMedia->testCollectParameters();

		static::assertSame(Base::TABSTOPS_PARAMETER . '<param name="cacheControl" value="onlyIfCached" />' . "\n", $result);
	}

	#[Group('units')]
	public function testCollectParametersWithNonFileDatasource(): void
	{
		$item = ['item_id' => 1, 'smil_playlist_item_id' => 123, 'datasource' => 'stream', 'flags' => 0];
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		$result = $this->concreteMedia->testCollectParameters();

		static::assertSame('', $result);
	}

	#[Group('units')]
	public function testCollectWithLoggableFlag(): void
	{
		$item = ['item_id' => 123, 'flags' => ItemFlags::loggable->value];
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		$result = $this->concreteMedia->testLoggable();

		static::assertSame(Base::TABSTOPS_PARAMETER . '<param name="logContentId" value="123" />' . "\n", $result);
	}

	#[Group('units')]
	public function testCollectWithOutLoggableFlag(): void
	{
		$item = ['item_id' => 123, 'flags' => 0];
		$this->concreteMedia = new ConcreteMedia($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub,);

		static::assertEmpty($this->concreteMedia->testLoggable());
	}


}
