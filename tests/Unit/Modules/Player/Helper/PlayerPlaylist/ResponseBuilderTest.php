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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ResponseBuilderTest extends TestCase
{
	private JsonResponseHandler&MockObject $jsonResponseHandlerMock;
	private ResponseInterface&Stub $responseStub;
	private Translator&MockObject $translatorMock;
	private ResponseBuilder $responseBuilder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->jsonResponseHandlerMock = $this->createMock(JsonResponseHandler::class);
		$this->translatorMock = $this->createMock(Translator::class);
		$this->responseStub = $this->createStub(ResponseInterface::class);

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
			->with($this->responseStub, 'Translated Message')
			->willReturn($this->responseStub);

		$this->responseBuilder->invalidPlayerId($this->responseStub);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInvalidPlaylistId(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('invalid_playlist_id', 'playlist')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseStub, 'Translated Message')
			->willReturn($this->responseStub);

		$this->responseBuilder->invalidPlaylistId($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPlayerNotReachable(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('player_not_reachable', 'player')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseStub, 'Translated Message')
			->willReturn($this->responseStub);

		$this->responseBuilder->playerNotReachable($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testNoPlaylistAssigned(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('no_playlist_assigned', 'player')
			->willReturn('Translated Message');

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseStub, 'Translated Message')
			->willReturn($this->responseStub);

		$this->responseBuilder->noPlaylistAssigned($this->responseStub);
	}
}
