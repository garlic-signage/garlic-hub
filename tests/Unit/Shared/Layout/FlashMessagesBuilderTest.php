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

namespace Tests\Unit\Shared\Layout;

use App\Shared\Layout\FlashMessagesBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Slim\Flash\Messages;

class FlashMessagesBuilderTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildReturnsErrorsWithCloseButtonAndSuccesses(): void
	{
		$flashStub = $this->createStub(Messages::class);
		$flashStub->method('hasMessage')->willReturnMap([['error', true], ['success', true]]);
		$flashStub->method('getMessage')->willReturnMap([['error', ['Failed']], ['success', ['Saved', 'Done']]]);

		static::assertSame([
			['MESSAGE_TYPE' => 'error', 'has_close_button' => true, 'MESSAGE_TEXT' => 'Failed'],
			['MESSAGE_TYPE' => 'success', 'has_close_button' => false, 'MESSAGE_TEXT' => 'Saved'],
			['MESSAGE_TYPE' => 'success', 'has_close_button' => false, 'MESSAGE_TEXT' => 'Done']
		], new FlashMessagesBuilder($flashStub)->build());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildWithoutMessagesIsEmpty(): void
	{
		$flashStub = $this->createStub(Messages::class);
		$flashStub->method('hasMessage')->willReturn(false);

		static::assertSame([], new FlashMessagesBuilder($flashStub)->build());
	}
}
