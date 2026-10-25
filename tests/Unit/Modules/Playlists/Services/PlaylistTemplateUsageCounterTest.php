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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistTemplateUsageCounter;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PlaylistTemplateUsageCounterTest extends TestCase
{
	private ItemsRepository&Stub $itemsRepositoryStub;
	private PlaylistTemplateUsageCounter $counter;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsRepositoryStub = $this->createStub(ItemsRepository::class);
		$this->counter             = new PlaylistTemplateUsageCounter($this->itemsRepositoryStub);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCountUsagesByTemplateIdsReturnsCountsPerTemplate(): void
	{
		$this->itemsRepositoryStub->method('countFileResourcesByTemplateId')
			->willReturnMap([[[3, 7, 9], [
				['template_id' => '3', 'count' => '2'],
				['template_id' => '7', 'count' => 5]
			]]]);

		static::assertSame([3 => 2, 7 => 5], $this->counter->countUsagesByTemplateIds([3, 7, 9]));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCountUsagesByTemplateIdsWithoutUsage(): void
	{
		$this->itemsRepositoryStub->method('countFileResourcesByTemplateId')->willReturnMap([[[3], []]]);

		static::assertSame([], $this->counter->countUsagesByTemplateIds([3]));
	}
}
