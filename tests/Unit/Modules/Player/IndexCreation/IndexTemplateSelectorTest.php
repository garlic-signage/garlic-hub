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

use App\Modules\Player\Enums\TemplateIndexFiles;
use App\Modules\Player\IndexCreation\IndexTemplateSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlayerEntities;

class IndexTemplateSelectorTest extends TestCase
{
	/**
	 * @return array<string,array{string,string,TemplateIndexFiles}> the model, the firmware, the template
	 */
	public static function players(): array
	{
		return [
			'iadea 2x00'               => ['XMP-6250', 'ADAPI/1.2.59.161', TemplateIndexFiles::XMP2XXX],
			'qbic'                     => ['TD-1050', 'v1.0.16', TemplateIndexFiles::XMP2XXX],
			'iadea 1x0'                => ['XDS-101', 'SK8855-ADAPI/2.0.5', TemplateIndexFiles::SIMPLE],
			'iadea 3x0'                => ['XMP-330', 'SK8855-ADAPI/2.0.5', TemplateIndexFiles::SIMPLE],
			'iadea 3x50'               => ['XMP-3250', 'x/1.0', TemplateIndexFiles::SIMPLE],
			'ids'                      => ['IDS-App', 'Android/1.0.180vv', TemplateIndexFiles::SIMPLE],
			'screenlite'               => ['ScreenliteWeb', 'screenlite-web/0.0.1', TemplateIndexFiles::SIMPLE],
			'compatible'               => ['Kathrein', 'x/1.0', TemplateIndexFiles::SIMPLE],
			'garlic, the build with the support' => ['Garlic', 'garlic-macOS/v0.6.0.566', TemplateIndexFiles::GARLIC],
			'garlic, a newer build'    => ['Garlic', 'garlic-macOS/v0.6.0.679', TemplateIndexFiles::GARLIC],
			'garlic, the build before' => ['Garlic', 'garlic-macOS/v0.6.0.565', TemplateIndexFiles::SIMPLE],
			'garlic, an old build'     => ['Garlic', 'garlic-linux/v0.5.0.101', TemplateIndexFiles::SIMPLE],
			'garlic with a suffix'     => ['Garlic', 'garlic-linux/v0.6.0.700L', TemplateIndexFiles::GARLIC],
			'garlic without a build'   => ['Garlic', 'garlic', TemplateIndexFiles::SIMPLE],
			'garlic with a big build'  => ['Garlic', 'garlic/v1.0.0.12000', TemplateIndexFiles::GARLIC],
		];
	}

	/**
	 * The index of a player is made with the template that its software understands.
	 */
	#[Group('units')]
	#[DataProvider('players')]
	public function testEveryPlayerGetsTheTemplateItUnderstands(string $model, string $firmware, TemplateIndexFiles $expected): void
	{
		$selector = new IndexTemplateSelector();

		static::assertSame($expected, $selector->select(PlayerEntities::create([], $model, $firmware)));
	}
}
