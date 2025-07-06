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
use App\Modules\Playlists\Helper\ExportSmil\items\Audio;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AudioTest extends TestCase
{
	private Config&MockObject $configMock;
	private Properties&MockObject $propertiesMock;
	private Trigger&MockObject $beginMock;
	private Trigger&MockObject $endMock;
	private Conditional&MockObject $conditionalMock;

	/**
	 * @throws Exception|\PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configMock = $this->createMock(Config::class);
		$this->propertiesMock = $this->createMock(Properties::class);
		$this->beginMock       = $this->createMock(Trigger::class);
		$this->endMock         = $this->createMock(Trigger::class);
		$this->conditionalMock = $this->createMock(Conditional::class);

	}

	#[Group('units')]
	public function testGetSmilElementTag(): void
	{
		$this->beginMock->method('hasTriggers')->willReturn(false);
		$this->endMock->method('hasTriggers')->willReturn(false);
		$this->conditionalMock->method('determineExprAttribute')->willReturn('');
		$this->propertiesMock->method('getVolume')->willReturn('soundLevel="100"');

		$item = ['item_id' => 1, 'item_name' => 'Sample Item', 'item_duration' => 500, 'flags' => 0, 'datasource' => 'file'];
		$audio = new Audio($this->configMock, $item, $this->propertiesMock, $this->beginMock, $this->endMock, $this->conditionalMock);

		$audio->setLink('/path/to/video.webm');

		$expected  = Base::TABSTOPS_TAG.'<audio xml:id="1" title="Sample Item" region="screen" src="path/to/video.webm" dur="500s" soundLevel="100">'."\n";
		$expected .= Base::TABSTOPS_PARAMETER.'<param name="cacheControl" value="onlyIfCached" />'."\n";
		$expected .= Base::TABSTOPS_TAG.'</audio>'."\n";

		static::assertSame($expected, $audio->getSmilElementTag());
	}
}
