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

namespace Tests\Unit\Framework\Exceptions;

use App\Framework\Exceptions\TranslatableModuleException;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TranslatableModuleExceptionTest extends TestCase
{
	#[Group('units')]
	public function testCarriesTranslationKeyAndReplacements(): void
	{
		$previous  = new Exception('db');
		$exception = new TranslatableModuleException('playlists', 'playlist_not_found', ['playlist_id' => 5], $previous);

		static::assertSame('playlists', $exception->getModuleName());
		static::assertSame('playlist_not_found', $exception->getTranslationKey());
		static::assertSame(['playlist_id' => 5], $exception->getReplacements());
		static::assertSame($previous, $exception->getPrevious());
	}

	#[Group('units')]
	public function testMessageIsTechnicalForTheLog(): void
	{
		$exception = new TranslatableModuleException('playlists', 'playlist_not_found', ['playlist_id' => 5]);

		static::assertSame('playlists: playlist_not_found {"playlist_id":5}', $exception->getMessage());
	}

	#[Group('units')]
	public function testMessageWithoutReplacements(): void
	{
		$exception = new TranslatableModuleException('playlists', 'no_rights');

		static::assertSame('playlists: no_rights', $exception->getMessage());
		static::assertSame([], $exception->getReplacements());
	}
}
