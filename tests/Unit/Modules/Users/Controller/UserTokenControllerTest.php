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


namespace Tests\Unit\Modules\Users\Controller;

use App\Framework\Core\CsrfToken;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Controller\UserTokenController;
use App\Modules\Users\Services\AclValidator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class UserTokenControllerTest extends TestCase
{
	private UserSession&MockObject $userSessionMock;
	private UserTokenService&MockObject $userServiceMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private AclValidator&MockObject $aclValidatorMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private UserTokenController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userSessionMock  = $this->createMock(UserSession::class);
		$this->userServiceMock  = $this->createMock(UserTokenService::class);
		$this->csrfTokenMock    = $this->createMock(CsrfToken::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);

		$this->requestMock         = $this->createMock(ServerRequestInterface::class);
		$this->responseMock        = $this->createMock(ResponseInterface::class);
		$this->streamInterfaceMock = $this->createMock(StreamInterface::class);

		$this->controller = new UserTokenController(
			$this->userSessionMock, $this->userServiceMock, $this->csrfTokenMock, $this->aclValidatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testRefreshSuccess(): void
	{
		$this->requestMock->method('getParsedBody')->willReturn([
			'csrf_token' => 'valid_token',
			'token' => 'valid_token'
		]);
		$this->csrfTokenMock->method('validateToken')->with('valid_token')->willReturn(true);
		$this->userServiceMock->method('findByTokenForAction')->with('valid_token')->willReturn(['purpose' => TokenPurposes::INITIAL_PASSWORD->value]);
		$this->userSessionMock->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('isAdmin')->with(1, ['purpose' => TokenPurposes::INITIAL_PASSWORD->value])->willReturn(true);
		$this->userServiceMock->expects($this->once())->method('refreshToken')
			->with('valid_token', TokenPurposes::INITIAL_PASSWORD->value)
			->willReturn(1);

		$this->mockJsonResponse(['success' => true]);

		$this->controller->refresh($this->requestMock, $this->responseMock);
	}


	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeleteWithInvalidCsrfToken(): void
	{
		$this->requestMock->method('getParsedBody')->willReturn(['csrf_token' => 'invalid_token']);
		$this->csrfTokenMock->method('validateToken')->with('invalid_token')->willReturn(false);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Csrf token mismatch.']);

		$this->controller->delete($this->requestMock, $this->responseMock);

	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeleteWithMissingToken(): void
	{
		$this->requestMock->method('getParsedBody')->willReturn(['csrf_token' => 'valid_token']);
		$this->csrfTokenMock->method('validateToken')->with('valid_token')->willReturn(true);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Token not transmitted.']);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeleteWithNonExistentToken(): void
	{
		$this->requestMock->method('getParsedBody')->willReturn([
			'csrf_token' => 'valid_token',
			'token' => 'non_existent_token'
		]);
		$this->csrfTokenMock->method('validateToken')->with('valid_token')->willReturn(true);
		$this->userServiceMock->method('findByTokenForAction')->with('non_existent_token')->willReturn(null);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Token not exists.']);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeleteWithInsufficientPermissions(): void
	{
		$this->requestMock->method('getParsedBody')->willReturn([
			'csrf_token' => 'valid_token',
			'token' => 'valid_token'
		]);
		$this->csrfTokenMock->method('validateToken')->with('valid_token')->willReturn(true);
		$this->userServiceMock->method('findByTokenForAction')->with('valid_token')->willReturn(['purpose' => 'sample']);
		$this->userSessionMock->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('isAdmin')->with(1, ['purpose' => 'sample'])->willReturn(false);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'No rights to handle tokens.']);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeleteSuccess(): void
	{
		$this->requestMock->method('getParsedBody')->willReturn([
			'csrf_token' => 'valid_token',
			'token' => 'valid_token'
		]);
		$this->csrfTokenMock->method('validateToken')->with('valid_token')->willReturn(true);
		$this->userServiceMock->method('findByTokenForAction')->with('valid_token')->willReturn(['purpose' => 'sample']);
		$this->userSessionMock->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('isAdmin')->with(1, ['purpose' => 'sample'])->willReturn(true);
		$this->userServiceMock->expects($this->once())->method('deleteByUserPurpose')
			->with('valid_token')
			->willReturn(1);

		$this->mockJsonResponse(['success' => true]);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeleteFails(): void
	{
		$this->requestMock->method('getParsedBody')->willReturn([
			'csrf_token' => 'valid_token',
			'token' => 'valid_token'
		]);
		$this->csrfTokenMock->method('validateToken')->with('valid_token')->willReturn(true);
		$this->userServiceMock->method('findByTokenForAction')->with('valid_token')->willReturn(['purpose' => 'sample']);
		$this->userSessionMock->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('isAdmin')->with(1, ['purpose' => 'sample'])->willReturn(true);
		$this->userServiceMock->expects($this->once())->method('deleteByUserPurpose')
			->with('valid_token')
			->willReturn(0);

		$this->mockJsonResponse(['success' => false, 'error_message' => 'Token not editable.']);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}


	/**
	 * @param array<string,mixed> $data
	 */
	private function mockJsonResponse(array $data): void
	{
		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->method('write')->with(json_encode($data));
		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'application/json')
			->willReturnSelf();
		$this->responseMock->method('withStatus')->with('200');
	}

}
