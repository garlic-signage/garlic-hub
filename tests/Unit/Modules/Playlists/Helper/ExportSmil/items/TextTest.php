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
use App\Modules\Playlists\Helper\ExportSmil\items\Text;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
	private Trigger&Stub $beginStub;
	private Trigger&Stub $endStub;
	private Conditional&Stub $conditionalStub;
	private Text $text;

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
		$this->text = new Text($configStub, $item, $propertiesStub, $this->beginStub, $this->endStub, $this->conditionalStub, );
	}

	#[Group('units')]
	public function testGetSmilElementTag(): void
	{
		$this->beginStub->method('hasTriggers')->willReturn(false);
		$this->endStub->method('hasTriggers')->willReturn(false);
		$this->conditionalStub->method('determineExprAttribute')->willReturn('');

		$this->text->setLink('/path/to/index.html');

		$expected  = Base::TABSTOPS_TAG.'<ref title="Sample Item" region="screen" src="path/to/index.html" dur="500s" type="text/html">'."\n";
		$expected .= Base::TABSTOPS_TAG.'</ref>'."\n";

		static::assertSame($expected, $this->text->createSmilTag());
	}
}
