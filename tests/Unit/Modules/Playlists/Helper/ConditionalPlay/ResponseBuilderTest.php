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


namespace Tests\Unit\Modules\Playlists\Helper\ConditionalPlay;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Playlists\Helper\ConditionalPlay\ResponseBuilder;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ResponseBuilderTest extends TestCase
{
	private JsonResponseHandler&MockObject $jsonResponseHandlerMock;
	private Translator&MockObject $translatorMock;
	private ResponseInterface&MockObject $responseMock;
	private ResponseBuilder $responseBuilder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->jsonResponseHandlerMock = $this->createMock(JsonResponseHandler::class);
		$this->translatorMock = $this->createMock(Translator::class);
		$this->responseMock = $this->createMock(ResponseInterface::class);

		$this->responseBuilder = new ResponseBuilder(
			$this->jsonResponseHandlerMock,
			$this->translatorMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInvalidItemId(): void
	{
		$translatedMessage = 'Invalid item ID';
		$this->translatorMock->expects($this->once())->method('translate')
			->with('invalid_item_id', 'playlists')
			->willReturn($translatedMessage);

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, $translatedMessage)
			->willReturn($this->responseMock);

		$result = $this->responseBuilder->invalidItemId($this->responseMock);

		self::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPlaylistNotFound(): void
	{
		$translatedMessage = 'Playlist not found';
		$this->translatorMock->expects($this->once())->method('translate')
			->with('playlist_not_found', 'playlists')
			->willReturn($translatedMessage);

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, $translatedMessage)
			->willReturn($this->responseMock);

		$result = $this->responseBuilder->playlistNotFound($this->responseMock);

		self::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testItemNotFound(): void
	{
		$translatedMessage = 'Item not found';
		$this->translatorMock->expects($this->once())->method('translate')
			->with('item_not_found', 'playlists')
			->willReturn($translatedMessage);

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, $translatedMessage)
			->willReturn($this->responseMock);

		$result = $this->responseBuilder->itemNotFound($this->responseMock);

		self::assertSame($this->responseMock, $result);
	}
}
