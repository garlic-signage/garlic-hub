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

namespace Tests\Unit\Shared\Acl;

use App\Framework\Config\Config;
use App\Shared\Acl\EditionAclModules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class EditionAclModulesTest extends TestCase
{
	/**
	 * @return array<string,array{string}>
	 */
	public static function editions(): array
	{
		return ['edge' => [Config::PLATFORM_EDITION_EDGE], 'core' => [Config::PLATFORM_EDITION_CORE], 'enterprise' => [Config::PLATFORM_EDITION_ENTERPRISE]];
	}

	#[Group('units')]
	#[DataProvider('editions')]
	public function testEveryEditionHasTheModulesWithRights(string $edition): void
	{
		static::assertSame(['users', 'mediapool', 'player', 'playlists', 'templates'], EditionAclModules::getModules($edition));
	}

	#[Group('units')]
	public function testAnUnknownEditionHasNoModules(): void
	{
		static::assertSame([], EditionAclModules::getModules('unknown'));
	}
}
