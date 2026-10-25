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

namespace App\Modules\Mediapool\Utils;

use Closure;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * A stream which takes only as many bytes as allowed. The first byte over the limit throws, so a download
 * into it stops at the limit and does not fill the disk, whatever the server announced about the size.
 */
final class SizeLimitedStream implements StreamInterface
{
	use StreamDecoratorTrait;

	private int $written = 0;

	/**
	 * @param Closure(int): Throwable $exceeded gives the exception for the number of bytes which were tried to write
	 */
	public function __construct(private readonly StreamInterface $stream, private readonly int $maxSize, private readonly Closure $exceeded)
	{
	}

	public function write(string $string): int
	{
		$this->written += strlen($string);
		if ($this->written > $this->maxSize)
			throw ($this->exceeded)($this->written);

		return $this->stream->write($string);
	}

	/**
	 * Required by the trait, which builds the stream lazily. This stream is given in the constructor.
	 *
	 * @codeCoverageIgnore
	 */
	protected function createStream(): StreamInterface
	{
		return $this->stream;
	}
}
