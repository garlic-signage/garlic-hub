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

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Helper\PlayerPlaylist\Orchestrator;
use App\Modules\Player\Helper\PlayerPlaylist\ResponseBuilder;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private UserSession&MockObject $userSessionMock;
	private BaseValidator&MockObject $validatorMock;
	private PlayerService&MockObject $playerServiceMock;
	private PlayerRestAPIService&MockObject $playerRestAPIServiceMock;
	private ResponseInterface&Stub $responseStub;
	private Orchestrator $orchestrator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->responseBuilderMock = $this->createMock(ResponseBuilder::class);
		$this->userSessionMock = $this->createMock(UserSession::class);
		$this->validatorMock = $this->createMock(BaseValidator::class);
		$this->playerServiceMock = $this->createMock(PlayerService::class);
		$this->playerRestAPIServiceMock = $this->createMock(PlayerRestAPIService::class);
		$this->responseStub = $this->createStub(ResponseInterface::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->userSessionMock,
			$this->validatorMock,
			$this->playerServiceMock,
			$this->playerRestAPIServiceMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateSucceed(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->playerServiceMock, $this->playerRestAPIServiceMock);
		$input = [
			'player_id' => '123',
			'playlist_id' => '456',
			BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_csrf_token'
		];

		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_csrf_token')
			->willReturn(true);

		$this->responseBuilderMock->expects($this->never())->method('invalidPlaylistId');

		$this->responseBuilderMock->expects($this->never())->method('csrfTokenMismatch');

		$result = $this->orchestrator->setInput($input)->validateForReplacePlaylist($this->responseStub);

		static::assertNull($result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithInvalidCsrfToken(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->playerServiceMock, $this->playerRestAPIServiceMock);
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_csrf_token')
			->willReturn(false);

		$this->responseBuilderMock->expects($this->once())->method('csrfTokenMismatch')
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([
			'playlist_id' => '0',
			BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_csrf_token'
		]);

		$result = $this->orchestrator->validateForReplacePlaylist($this->responseStub);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithInvalidPlayerId(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->playerServiceMock, $this->playerRestAPIServiceMock);
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_csrf_token')
			->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('invalidPlayerId')
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([
			'playlist_id' => '23',
			BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_csrf_token'
		]);

		$result = $this->orchestrator->validateForReplacePlaylist($this->responseStub);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testReplaceMasterPlaylistSuccess(): void
	{
		$this->playerRestAPIServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();

		$data = ['affected' => 1, 'playlist_name' => 'Test Playlist'];

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerServiceMock->expects($this->once())->method('setUID')->with(1);

		$this->playerServiceMock->expects($this->once())->method('replaceMasterPlaylist')
			->with(123, 456)
			->willReturn($data);

		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseStub, ['playlist_name' => 'Test Playlist'])
			->willReturn($this->responseStub);

		$this->orchestrator->replaceMasterPlaylist($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testReplaceMasterPlaylistFailure(): void
	{
		$this->playerRestAPIServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerServiceMock->expects($this->once())->method('setUID')->with(1);
		$this->playerServiceMock->expects($this->once())->method('replaceMasterPlaylist')
			->with(123, 456)
			->willReturn(['affected' => 0]);

		$this->playerServiceMock->expects($this->once())->method('getErrorMessagesAsString')->willReturn('Some error occurred');

		$this->responseBuilderMock->expects($this->once())->method('generalError')
			->with($this->responseStub, 'Some error occurred')
			->willReturn($this->responseStub);

		$this->orchestrator->replaceMasterPlaylist($this->responseStub);
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCheckPlayerNotFound(): void
	{
		$this->playerRestAPIServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerServiceMock->expects($this->once())->method('setUID')->with(1);
		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
			->with(123)
			->willReturn([]);

		$this->responseBuilderMock->expects($this->once())->method('playerNotFound')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput(['player_id' => '123']);
		$result = $this->orchestrator->checkPlayer($this->responseStub);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckPlayerNotReachable(): void
	{
		$this->playerRestAPIServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerServiceMock->expects($this->once())->method('setUID')->with(1);
		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
			->with(123)
			->willReturn(['is_intranet' => 0, 'playlist_id' => 1, 'api_endpoint' => '', 'player_name' => 'Test']);

		$this->responseBuilderMock->expects($this->once())->method('playerNotReachable')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput(['player_id' => '123']);
		$result = $this->orchestrator->checkPlayer($this->responseStub);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckPlayerNoPlaylistAssigned(): void
	{
		$this->playerRestAPIServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerServiceMock->expects($this->once())->method('setUID')->with(1);
		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
			->with(123)
			->willReturn(['is_intranet' => 1, 'playlist_id' => 0, 'api_endpoint' => '', 'player_name' => 'Test']);

		$this->responseBuilderMock->expects($this->once())->method('noPlaylistAssigned')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput(['player_id' => '123']);
		$result = $this->orchestrator->checkPlayer($this->responseStub);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckPlayerValid(): void
	{
		$this->playerRestAPIServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerServiceMock->expects($this->once())->method('setUID')->with(1);
		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
			->with(123)
			->willReturn(['is_intranet' => 1, 'playlist_id' => 1, 'api_endpoint' => '', 'player_name' => 'Test']);

		$this->responseBuilderMock->expects($this->never())->method('playerNotFound');
		$this->responseBuilderMock->expects($this->never())->method('playerNotReachable');
		$this->responseBuilderMock->expects($this->never())->method('noPlaylistAssigned');

		$result = $this->orchestrator->checkPlayer($this->responseStub);

		static::assertNull($result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testPushPlaylistSuccess(): void
	{
		$this->playerServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();
		$player = ['is_intranet' => 1, 'playlist_id' => 1, 'api_endpoint' => 'api_endpoint', 'player_name' => 'Test'];
		$this->orchestrator->setPlayer($player);

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerRestAPIServiceMock->expects($this->once())->method('setUID')->with(1);

		$this->playerRestAPIServiceMock->expects($this->once())->method('authenticate')
			->with('api_endpoint', 'admin', '', 123)
			->willReturn(true);

		$this->playerRestAPIServiceMock->expects($this->once())->method('switchToDefaultContentUrl')
			->with('api_endpoint', 123)
			->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseStub, ['message' => 'Playlist pushed successfully to Test.'])
			->willReturn($this->responseStub);

		$this->orchestrator->pushPlaylist($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testPushPlaylistAuthenticationFailure(): void
	{
		$this->playerServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();
		$player = ['is_intranet' => 1, 'playlist_id' => 1, 'api_endpoint' => 'api_endpoint', 'player_name' => 'Test'];
		$this->orchestrator->setPlayer($player);

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerRestAPIServiceMock->expects($this->once())->method('setUID')->with(1);

		$this->playerRestAPIServiceMock->expects($this->once())->method('authenticate')
			->with('api_endpoint', 'admin', '', 123)
			->willReturn(false);

		$errorMessage = 'Authentication failed';
		$this->playerRestAPIServiceMock->expects($this->once())->method('getErrorMessages')
			->willReturn([$errorMessage]);

		$this->responseBuilderMock->expects($this->once())->method('generalError')
			->with($this->responseStub, $errorMessage)
			->willReturn($this->responseStub);

		$this->orchestrator->pushPlaylist($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testPushPlaylistSwitchFailure(): void
	{
		$this->playerServiceMock->expects($this->never())->method($this->anything());
		$this->fillData();
		$player = ['is_intranet' => 1, 'playlist_id' => 1, 'api_endpoint' => 'api_endpoint', 'player_name' => 'Test'];
		$this->orchestrator->setPlayer($player);

		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->playerRestAPIServiceMock->expects($this->once())->method('setUID')->with(1);

		$this->playerRestAPIServiceMock->expects($this->once())->method('authenticate')
			->with('api_endpoint', 'admin', '', 123)
			->willReturn(true);

		$this->playerRestAPIServiceMock->expects($this->once())->method('switchToDefaultContentUrl')
			->with('api_endpoint', 123)
			->willReturn(false);

		$errorMessage = 'Switching to default content URL failed';
		$this->playerRestAPIServiceMock->expects($this->once())->method('getErrorMessages')
			->willReturn([$errorMessage]);

		$this->responseBuilderMock->expects($this->once())->method('generalError')
			->with($this->responseStub, $errorMessage)
			->willReturn($this->responseStub);

		$this->orchestrator->pushPlaylist($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function fillData(): void
	{
		$input = [
			'player_id' => '123',
			'playlist_id' => '456',
			BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_csrf_token'
		];
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_csrf_token')
			->willReturn(true);
		$this->orchestrator->setInput($input)->validateForReplacePlaylist($this->responseStub);

	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
