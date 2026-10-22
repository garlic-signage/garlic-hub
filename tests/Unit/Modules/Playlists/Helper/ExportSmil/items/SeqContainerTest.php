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

use App\Framework\Config\Config;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use App\Modules\Playlists\Helper\ExportSmil\items\SeqContainer;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class SeqContainerTest extends TestCase
{
	private Config&Stub $configStub;
	private Trigger&Stub $beginStub;
	private Trigger&Stub $endStub;
	private Conditional&Stub $conditionalStub;
	private Properties $propertiesStub;
	private SeqContainer $seqContainer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub = $this->createStub(Config::class);
		$this->beginStub       = $this->createStub(Trigger::class);
		$this->endStub         = $this->createStub(Trigger::class);
		$this->conditionalStub = $this->createStub(Conditional::class);
		$this->propertiesStub = $this->createStub(Properties::class);
	}

	#[Group('units')]
	public function testGetSmilElementTag(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(false);

		$item = ['item_id' => 1, 'item_name' => 'foo', 'file_resource' => '12345', 'time_limit' => null];

		$this->seqContainer = new SeqContainer(
			$this->configStub,
			$item,
			$this->propertiesStub,
			$this->beginStub,
			$this->endStub,
			$this->conditionalStub
		);

		$expected = Base::TABSTOPS_TAG . '<seq title="foo" >' . "\n" .
			Base::TABSTOPS_PARAMETER . '{ITEMS_12345}' . "\n" .
			Base::TABSTOPS_TAG . '</seq>' . "\n";

		static::assertSame($expected, $this->seqContainer->createSmilTag());
	}

	#[Group('units')]
	public function testGetSmilElementTagWithDur(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(false);

		$item = ['item_id' => 1, 'item_name' => 'foo', 'file_resource' => '12345', 'time_limit' => 15, 'owner_duration' => 20];

		$this->seqContainer = new SeqContainer(
			$this->configStub,
			$item,
			$this->propertiesStub,
			$this->beginStub,
			$this->endStub,
			$this->conditionalStub
		);

		$expected = Base::TABSTOPS_TAG . '<seq title="foo" dur="15s" >' . "\n" .
			Base::TABSTOPS_PARAMETER . '{ITEMS_12345}' . "\n" .
			Base::TABSTOPS_TAG . '</seq>' . "\n";

		static::assertSame($expected, $this->seqContainer->createSmilTag());
	}

	#[Group('units')]
	public function testGetSmilElementTagWithTriggers(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(true);
		$item = ['item_id' => 1, 'item_name' => 'boo', 'file_resource' => '12345', 'time_limit' => 0, 'owner_duration' => 20];

		$this->seqContainer = new SeqContainer(
		$this->configStub,
			$item,
			$this->propertiesStub,
			$this->beginStub,
			$this->endStub,
			$this->conditionalStub
		);
		$this->seqContainer->setBelongsToMasterPlaylist(true);

		static::assertSame('', $this->seqContainer->getSmilElementTag());
	}

	#[Group('units')]
	public function testGetSmilElementTagWithTriggersNoMaster(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(true);
		$item = ['item_id' => 1, 'item_name' => 'boo', 'file_resource' => '12345', 'time_limit' => 0, 'owner_duration' => 20];

		$this->seqContainer = new SeqContainer(
			$this->configStub,
			$item,
			$this->propertiesStub,
			$this->beginStub,
			$this->endStub,
			$this->conditionalStub
		);
		$this->seqContainer->setBelongsToMasterPlaylist(false);

		$expected = Base::TABSTOPS_TAG . '<seq title="boo" >' . "\n" .
			Base::TABSTOPS_PARAMETER . '{ITEMS_12345}' . "\n" .
			Base::TABSTOPS_TAG . '</seq>' . "\n";

		static::assertSame($expected, $this->seqContainer->getSmilElementTag());
	}

	#[Group('units')]
	public function testGetElementLinkWithScheduledStartDate(): void
	{
		$item = ['external_link' => 'example.com'];

		$this->seqContainer = new SeqContainer(
			$this->configStub,
			$item,
			$this->propertiesStub,
			$this->beginStub,
			$this->endStub,
			$this->conditionalStub
		);

		$expected = Base::TABSTOPS_TAG . '{ITEMS_0#example.com}' . "\n";

		static::assertSame($expected, $this->seqContainer->getElementLink());
	}

	#[Group('units')]
	public function testGetPrefetchTag(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(false);

		$item = ['file_resource' => '12345'];

		$this->seqContainer = new SeqContainer(
			$this->configStub,
			$item,
			$this->propertiesStub,
			$this->beginStub,
			$this->endStub,
			$this->conditionalStub
		);

		$expected = Base::TABSTOPS_TAG . '{PREFETCH_12345}' . "\n";

		static::assertSame($expected, $this->seqContainer->getPrefetchTag());
	}
}
