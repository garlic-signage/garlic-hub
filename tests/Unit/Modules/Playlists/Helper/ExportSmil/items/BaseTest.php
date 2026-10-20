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
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConcreteBase extends Base
{
	public function getPrefetchTag(): string
	{
		return 'prefetch';
	}

	public function getSmilElementTag(): string
	{
		return $this->createSmilTag();
	}

	public function testTrigger(): string
	{
		return $this->determineBeginEndTrigger();
	}

	public function testCollectAttributes(): string
	{
		return $this->collectAttributes();
	}

	public function testInsertXmlId(): string
	{
		return $this->insertXmlId();
	}

	public function testEncodeTitle(): string
	{
		return $this->encodeItemNameForTitleTag();
	}

	public function testDetermineDuration(): string
	{
		return $this->determineDuration();
	}

	public function createSmilTag(): string
	{
		return 'smilElementTag';
	}
}

class BaseTest extends TestCase
{
	private Config&Stub $configStub;
	private Trigger&Stub $beginStub;
	private Trigger&Stub $endStub;
	private Conditional&Stub $conditionalStub;
	private Properties&Stub $propertiesStub;
	private ConcreteBase $concreteBase;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub      = $this->createStub(Config::class);
		$this->propertiesStub  = $this->createStub(Properties::class);
		$this->beginStub       = $this->createStub(Trigger::class);
		$this->endStub         = $this->createStub(Trigger::class);
		$this->conditionalStub = $this->createStub(Conditional::class);

		$item = ['item_id' => 1, 'item_name' => 'Example Title', 'item_duration' => 1000];
		$this->concreteBase = new ConcreteBase($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub);
	}

	#[Group('units')]
	public function testDetermineBeginEndTriggerNoMaster(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(true);
		$this->beginStub->method('determineTrigger')->willReturn('trigger_begin');
		$this->endStub->method('hasTriggers')->willReturn(true);
		$this->endStub->method('determineTrigger')->willReturn('trigger_end');

		$result = $this->concreteBase->testTrigger();
		static::assertEmpty($result);
	}

	#[Group('units')]
	public function testDetermineBeginEndTrigger(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(true);
		$this->beginStub->method('determineTrigger')->willReturn('trigger_begin');
		$this->endStub->method('hasTriggers')->willReturn(true);
		$this->endStub->method('determineTrigger')->willReturn('trigger_end');

		$this->concreteBase->setBelongsToMasterPlaylist(true);
		$result = $this->concreteBase->testTrigger();
		static::assertSame('begin="trigger_begin" end="trigger_end" ', $result);
	}

	#[Group('units')]
	public function testDetermineBeginEndTriggerBeginOnly(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(true);
		$this->beginStub->method('determineTrigger')->willReturn('trigger_begin');
		$this->endStub->method('hasTriggers')->willReturn(false);

		$this->concreteBase->setBelongsToMasterPlaylist(true);
		$result = $this->concreteBase->testTrigger();
		static::assertSame('begin="trigger_begin" ', $result);
	}

	#[Group('units')]
	public function testDetermineBeginEndTriggerEndOnly(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(false);
		$this->endStub->method('hasTriggers')->willReturn(true);
		$this->endStub->method('determineTrigger')->willReturn('trigger_end');

		$this->concreteBase->setBelongsToMasterPlaylist(true);
		$result = $this->concreteBase->testTrigger();
		static::assertSame('end="trigger_end" ', $result);
	}

	#[Group('units')]
	public function testCollectAttributes(): void
	{
		$this->conditionalStub->method('determineExprAttribute')->willReturn('expr="" ');

		$result = $this->concreteBase->testCollectAttributes();
		static::assertSame('expr="" title="Example Title" ', $result);
	}

	#[Group('units')]
	public function testInsertXmlIdForNonMasterPlaylist(): void
	{
		// master is default false
		$result = $this->concreteBase->testInsertXmlId();
		static::assertEmpty($result);
	}

	#[Group('units')]
	public function testInsertXmlIdForMasterPlaylist(): void
	{
		$this->concreteBase->setTouches([1 => 12]);
		$this->concreteBase->setBelongsToMasterPlaylist(true);
		$result = $this->concreteBase->testInsertXmlId();

		static::assertSame('xml:id="'.Base::ID_PREFIX.'1" ', $result);
	}

	#[Group('units')]
	public function testGetExclusiveWithTriggers(): void
	{
		$this->concreteBase->setBelongsToMasterPlaylist(true);
		$this->beginStub->method('hasTriggers')->willReturn(true);
		$this->beginStub->method('determineTrigger')->willReturn('trigger_begin');

		$result = $this->concreteBase->getExclusive();

		static::assertStringContainsString('<priorityClass>', $result);
		static::assertStringContainsString('smilElementTag', $result);
		static::assertStringContainsString('</priorityClass>', $result);
	}

	public function testGetExclusiveWithTriggersNoMaster(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(true);
		$this->beginStub->method('determineTrigger')->willReturn('trigger_begin');

		$result = $this->concreteBase->getExclusive();
		static::assertEmpty($result);
	}


	#[Group('units')]
	public function testGetExclusiveWithoutTriggers(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(false);

		$result = $this->concreteBase->getExclusive();

		static::assertSame('', $result);
	}

	#[Group('units')]
	public function testEncodeItemNameForTitleTag(): void
	{
		$result = $this->concreteBase->testEncodeTitle();
		static::assertStringContainsString('title="Example Title" ', $result);
	}

	#[Group('units')]
	public function testEncodeItemNameForTitleTagWithSpecialCharacters(): void
	{
		$item = ['item_id' => 1, 'item_name' => 'Special <Title> & $^', 'item_duration' => 1000];
		$this->concreteBase = new ConcreteBase($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub);

		$result = $this->concreteBase->testEncodeTitle();
		static::assertStringContainsString('title="Special &lt;Title&gt; &amp; $^" ', $result);
	}

	#[Group('units')]
	public function testEncodeItemNameForTitleTagWithAmpersand(): void
	{
		$item = ['item_id' => 1, 'item_name' => 'Title & Subtitle', 'item_duration' => 1000];
		$this->concreteBase = new ConcreteBase($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub);

		$result = $this->concreteBase->testEncodeTitle();
		static::assertStringContainsString('title="Title &amp; Subtitle" ', $result);
	}

	#[Group('units')]
	public function testDetermineDuration(): void
	{
		$result = $this->concreteBase->testDetermineDuration();
		static::assertStringContainsString('dur="1000s"', $result);
	}

	#[Group('units')]
	public function testEncodeItemNameForTitleTagWithNullName(): void
	{
		$item = ['item_id' => 1, 'item_name' => null, 'item_duration' => 0];
		$this->concreteBase = new ConcreteBase($this->configStub, $item, $this->propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub);

		$result = $this->concreteBase->testDetermineDuration();
		static::assertEmpty($result);
	}
}
