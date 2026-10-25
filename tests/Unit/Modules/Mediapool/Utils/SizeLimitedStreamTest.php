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

namespace Tests\Unit\Modules\Mediapool\Utils;

use App\Modules\Mediapool\Utils\SizeLimitedStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SizeLimitedStreamTest extends TestCase
{
	private function limited(int $max): SizeLimitedStream
	{
		$memory = fopen('php://memory', 'w+');
		static::assertIsResource($memory);

		return new SizeLimitedStream(Utils::streamFor($memory), $max, static fn(int $size): RuntimeException => new RuntimeException('too big: ' . $size));
	}

	#[Group('units')]
	public function testBytesUpToTheLimitAreWritten(): void
	{
		$stream = $this->limited(10);

		static::assertSame(4, $stream->write('1234'));
		static::assertSame(6, $stream->write('567890'));

		$stream->rewind();
		static::assertSame('1234567890', $stream->getContents());
		static::assertSame(10, $stream->getSize());
	}

	#[Group('units')]
	public function testTheFirstByteOverTheLimitThrowsWithTheSizeWhichWasTried(): void
	{
		$stream = $this->limited(10);
		$stream->write('123456');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('too big: 11');

		$stream->write('78901');
	}

	/**
	 * What was refused is not written: the stream keeps what was under the limit.
	 */
	#[Group('units')]
	public function testARefusedPieceIsNotWritten(): void
	{
		$stream = $this->limited(5);
		$stream->write('123');

		try
		{
			$stream->write('45678');
			static::fail('Expected an exception');
		}
		catch (RuntimeException)
		{
			static::addToAssertionCount(1);
		}

		static::assertSame(3, $stream->getSize());
	}

	#[Group('units')]
	public function testAnEmptyWriteIsAlwaysAllowed(): void
	{
		static::assertSame(0, $this->limited(0)->write(''));
	}
}
