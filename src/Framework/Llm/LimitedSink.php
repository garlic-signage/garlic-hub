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

namespace App\Framework\Llm;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * Where the answer of an endpoint is written while it comes. Above the limit it takes nothing more: curl stops the
 * transfer then (write error), also for an answer without Content-Length.
 */
final class LimitedSink implements StreamInterface
{
	use StreamDecoratorTrait;

	private int $written = 0;
	/** the stream of StreamDecoratorTrait */
	private StreamInterface $stream;

	public function __construct(private readonly int $limit)
	{
		$this->stream = Utils::streamFor(Utils::tryFopen('php://temp', 'r+'));
	}

	public function write(string $string): int
	{
		$this->written += strlen($string);
		if ($this->written > $this->limit)
			return 0;

		return $this->stream->write($string);
	}
}
