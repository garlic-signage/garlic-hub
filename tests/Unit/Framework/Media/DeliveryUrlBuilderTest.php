<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program. If not, see <https://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace Tests\Unit\Framework\Media;

use App\Framework\Media\DeliveryUrlBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class DeliveryUrlBuilderTest extends TestCase
{
	private DeliveryUrlBuilder $builder;

	protected function setUp(): void
	{
		parent::setUp();
		$this->builder = new DeliveryUrlBuilder('https://content.server.com');
	}

	#[Group('units')]
	public function testMediapoolUrlHasFileNameInPath(): void
	{
		static::assertSame('https://content.server.com/delivery.php/abc123.mp4', $this->builder->mediapoolUrl('abc123', 'mp4'));
	}

	#[Group('units')]
	public function testTemplateUrlUsesTplSource(): void
	{
		static::assertSame('https://content.server.com/delivery.php/tpl/13.jpg', $this->builder->templateUrl(13, 'jpg'));
	}

	#[Group('units')]
	public function testTemplateUrlTakesAStringId(): void
	{
		static::assertSame('https://content.server.com/delivery.php/tpl/13.jpg', $this->builder->templateUrl('13', 'jpg'));
	}

	#[Group('units')]
	public function testASlashAtTheEndOfTheServerUrlGivesNoDoubleSlash(): void
	{
		$builder = new DeliveryUrlBuilder('https://content.server.com/');

		static::assertSame('https://content.server.com/delivery.php/abc123.mp4', $builder->mediapoolUrl('abc123', 'mp4'));
		static::assertSame('https://content.server.com/delivery.php/tpl/13.jpg', $builder->templateUrl(13, 'jpg'));
	}
}
