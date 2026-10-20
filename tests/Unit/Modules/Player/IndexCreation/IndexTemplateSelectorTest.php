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

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\Enums\TemplateIndexFiles;
use App\Modules\Player\IndexCreation\IndexTemplateSelector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class IndexTemplateSelectorTest extends TestCase
{
	private IndexTemplateSelector $selector;
	private PlayerEntity&Stub $playerEntityStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->selector = new IndexTemplateSelector();
		$this->playerEntityStub = $this->createStub(PlayerEntity::class);
	}

	#[Group('units')]
	public function testSelectReturnsXmp2xxxForIadeaXmp2x00(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::IADEA_XMP2X00);

		$result = $this->selector->select($this->playerEntityStub);

		static::assertSame(TemplateIndexFiles::XMP2XXX, $result);
	}

	#[Group('units')]
	public function testSelectReturnsXmp2xxxForQBic(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::QBIC);

		$result = $this->selector->select($this->playerEntityStub);

		static::assertSame(TemplateIndexFiles::XMP2XXX, $result);
	}

	#[Group('units')]
	public function testSelectReturnsGarlicForGarlicWithValidFirmware(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::GARLIC);

		$this->playerEntityStub
			->method('getFirmwareVersion')
			->willReturn('1.0.566');

		$result = $this->selector->select($this->playerEntityStub);

		static::assertSame(TemplateIndexFiles::GARLIC, $result);
	}

	#[Group('units')]
	public function testSelectReturnsSimpleForGarlicWithInvalidFirmware(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::GARLIC);

		$this->playerEntityStub
			->method('getFirmwareVersion')
			->willReturn('1.0.565');

		$result = $this->selector->select($this->playerEntityStub);

		static::assertSame(TemplateIndexFiles::SIMPLE, $result);
	}

	#[Group('units')]
	public function testSelectReturnsSimpleForIadeaXmp1x0(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::IADEA_XMP1X0);

		$result = $this->selector->select($this->playerEntityStub);

		static::assertSame(TemplateIndexFiles::SIMPLE, $result);
	}

	#[Group('units')]
	public function testSelectReturnsSimpleForScreenLite(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::SCREENLITE);

		$result = $this->selector->select($this->playerEntityStub);

		static::assertSame(TemplateIndexFiles::SIMPLE, $result);
	}
	#[Group('units')]
	public function testSelectReturnsSimpleForUnknownModel(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::UNKNOWN);

		$result = $this->selector->select($this->playerEntityStub);

		static::assertSame(TemplateIndexFiles::SIMPLE, $result);
	}
}
