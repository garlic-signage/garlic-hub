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

namespace Tests\Unit\Modules\Playlists\Helper\Templates;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Playlists\Helper\Templates\Orchestrator;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Helper\Composer\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private ItemsService&MockObject $itemsServiceMock;
	private Orchestrator $orchestrator;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsServiceMock = $this->createMock(ItemsService::class);
		$configStub             = $this->createStub(Config::class);
		$configStub->method('getConfigValue')->willReturn('https://example.com');

		$this->orchestrator = new Orchestrator(
			$this->itemsServiceMock,
			$this->createStub(ExportImage::class),
			$this->createStub(TemplatePreparer::class),
			$configStub,
			new MediaUrlSigner('secret')
		);
	}

	/**
	 * The orchestrator must be constructable without touching the service.
	 */
	#[Group('units')]
	public function testConstructorDoesNotTouchItemsService(): void
	{
		$this->itemsServiceMock->expects($this->never())->method($this->anything());

		static::assertSame('', $this->orchestrator->getContent());
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testCheckRightsFetchesItem(): void
	{
		$this->itemsServiceMock->expects($this->once())->method('fetchItemById')->with(9)
			->willReturn(['item_id' => 9]);

		static::assertTrue($this->orchestrator->checkRights(9));
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testCheckRightsWithoutAccess(): void
	{
		$this->itemsServiceMock->expects($this->once())->method('fetchItemById')
			->willThrowException(new ModuleException('playlists', 'No rights.'));

		static::assertFalse($this->orchestrator->checkRights(9));
	}
}
