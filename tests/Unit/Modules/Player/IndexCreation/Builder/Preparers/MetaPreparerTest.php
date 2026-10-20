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

namespace Tests\Unit\Modules\Player\IndexCreation\Builder\Preparers;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\IndexCreation\Builder\Preparers\MetaPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class MetaPreparerTest extends TestCase
{
	private PlayerEntity&Stub $playerEntityStub;
	private MetaPreparer $preparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityStub = $this->createStub(PlayerEntity::class);
		$this->preparer   = new MetaPreparer($this->playerEntityStub);
	}

	#[Group('units')]
	public function testPrepareReturnsExpectedMetaArray(): void
	{
		$this->playerEntityStub->method('getPlayerName')->willReturn('Test Player');
		$this->playerEntityStub->method('getPlaylistName')->willReturn('Test Playlist');
		$this->playerEntityStub->method('getRefresh')->willReturn(120);

		$result = $this->preparer->prepare();

		static::assertEquals(
			[['TITLE' => 'Test Player - Test Playlist', 'REFRESH_TIME' => 120]],
			$result
		);
	}
}
