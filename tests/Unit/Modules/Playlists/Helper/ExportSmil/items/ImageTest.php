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
use App\Modules\Playlists\Helper\ExportSmil\items\Image;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
	private Trigger&Stub $beginStub;
	private Trigger&Stub $endStub;
	private Conditional&Stub $conditionalStub;
	private Image $image;

	/**
	 * @throws Exception|\PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$configStub = $this->createStub(Config::class);
		$propertiesStub = $this->createStub(Properties::class);
		$this->beginStub       = $this->createStub(Trigger::class);
		$this->endStub         = $this->createStub(Trigger::class);
		$this->conditionalStub = $this->createStub(Conditional::class);

		$item = ['item_id' => 1, 'item_name' => 'Sample Item', 'item_duration' => 500, 'flags' => 0, 'datasource' => 'file'];
		$this->image = new Image($configStub, $item, $propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub, );
	}

	#[Group('units')]
	public function testGetSmilElementTag(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(false);
		$this->endStub->method('hasTriggers')->willReturn(false);
		$this->conditionalStub->method('determineExprAttribute')->willReturn('');

		$this->image->setLink('/path/to//image.jpg');

		$expected  = Base::TABSTOPS_TAG.'<img title="Sample Item" region="screen" src="path/to//image.jpg" dur="500s" >'."\n";
		$expected .= Base::TABSTOPS_PARAMETER.'<param name="cacheControl" value="onlyIfCached" />'."\n";
		$expected .= Base::TABSTOPS_TAG.'</img>'."\n";

		static::assertSame($expected, $this->image->createSmilTag());
	}

}
