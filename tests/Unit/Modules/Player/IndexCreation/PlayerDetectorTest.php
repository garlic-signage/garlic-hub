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

use App\Framework\Config\Config;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\PlayerDetector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class PlayerDetectorTest extends TestCase
{
	private PlayerDetector $detector;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$configStub = $this->createStub(Config::class);

		$this->detector = new PlayerDetector($configStub);
	}

	#[Group('units')]
	public function testDetectModelIdForIADEAXMP1X0(): void
	{
		$this->detector->detectModelId('XMP-120');
		static::assertSame(PlayerModel::IADEA_XMP1X0, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForIADEAXMP3X0(): void
	{
		$this->detector->detectModelId('XMP-320');
		static::assertSame(PlayerModel::IADEA_XMP3X0, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForIADEAXMP3X50(): void
	{
		$this->detector->detectModelId('XMP-3250');
		static::assertSame(PlayerModel::IADEA_XMP3X50, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForCompatible(): void
	{
		$this->detector->detectModelId('fs5-player');
		static::assertSame(PlayerModel::COMPATIBLE, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForIADEAXMP2X00(): void
	{
		$this->detector->detectModelId('XMP-2200');
		static::assertSame(PlayerModel::IADEA_XMP2X00, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForGarlic(): void
	{
		$this->detector->detectModelId('Garlic');
		static::assertSame(PlayerModel::GARLIC, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForIDS(): void
	{
		$this->detector->detectModelId('IDS-App');
		static::assertSame(PlayerModel::IDS, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForQBIC(): void
	{
		$this->detector->detectModelId('BXP-202');
		static::assertSame(PlayerModel::QBIC, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForScreenlite(): void
	{
		$this->detector->detectModelId('ScreenliteWeb');
		static::assertSame(PlayerModel::SCREENLITE, $this->detector->getModelId());
	}

	#[Group('units')]
	public function testDetectModelIdForUnknown(): void
	{
		$this->detector->detectModelId('NonExistingModel');
		static::assertSame(PlayerModel::UNKNOWN, $this->detector->getModelId());
	}


}
