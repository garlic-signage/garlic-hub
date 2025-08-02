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


namespace Tests\Unit\Modules\Player\Helper\PlayerPlaylist;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Player\Helper\PlayerPlaylist\ResponseBuilder;
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
	private ResponseInterface&MockObject $responseMock;
	private Translator&MockObject $translatorMock;
	private ResponseBuilder $responseBuilder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->jsonResponseHandlerMock = $this->createMock(JsonResponseHandler::class);
		$this->translatorMock = $this->createMock(Translator::class);
		$this->responseMock = $this->createMock(ResponseInterface::class);

		$this->responseBuilder = new ResponseBuilder(
			$this->jsonResponseHandlerMock,
			$this->translatorMock
		);
	}

	#[Group('units')]
	public function testInvalidPlayerId(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('invalid_player_id', 'player')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, 'Translated Message')
			->willReturn($this->responseMock);

		$this->responseBuilder->invalidPlayerId($this->responseMock);
	}


	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInvalidPlaylistId(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('invalid_playlist_id', 'playlist')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, 'Translated Message')
			->willReturn($this->responseMock);

		$this->responseBuilder->invalidPlaylistId($this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPlayerNotFound(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('player_not_found', 'player')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, 'Translated Message')
			->willReturn($this->responseMock);

		$this->responseBuilder->playerNotFound($this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPlayerNotReachable(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('player_not_reachable', 'player')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, 'Translated Message')
			->willReturn($this->responseMock);

		$this->responseBuilder->playerNotReachable($this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testNoPlaylistAssigned(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('no_playlist_assigned', 'player')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, 'Translated Message')
			->willReturn($this->responseMock);

		$this->responseBuilder->noPlaylistAssigned($this->responseMock);
	}
}
