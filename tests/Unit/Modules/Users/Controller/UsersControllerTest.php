<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Security\CsrfToken;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Modules\Users\Controller\UsersController;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\Services\UsersDatatableService;
use App\Modules\Users\UserStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UsersControllerTest extends TestCase
{
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private UsersDatatableService&MockObject $usersServiceMock;
	private UsersAdminService&MockObject $usersAdminServiceMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private Parameters&MockObject $parametersMock;
	private JsonResponseHandler&MockObject $responseHandlerMock;
	private UsersController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestStub         = $this->createStub(ServerRequestInterface::class);
		$this->responseStub        = $this->createStub(ResponseInterface::class);
		$this->parametersMock      = $this->createMock(Parameters::class);
		$this->usersServiceMock    = $this->createMock(UsersDatatableService::class);
		$this->responseHandlerMock = $this->createMock(JsonResponseHandler::class);
		$this->usersAdminServiceMock = $this->createMock(UsersAdminService::class);
		$this->csrfTokenMock       = $this->createMock(CsrfToken::class);

		$this->controller = new UsersController(
			$this->usersServiceMock,
			$this->usersAdminServiceMock,
			$this->parametersMock,
			$this->responseHandlerMock,
			$this->csrfTokenMock
		);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFindByNameReturnsPlainList(): void
	{
		$this->expectUntouched($this->usersAdminServiceMock, $this->csrfTokenMock);
		$args = ['username' => 'testUser'];

		$this->parametersMock->expects($this->once())
			->method('addParameter')
			->with(Parameters::PARAMETER_FROM_STATUS, ScalarType::INT, UserStatus::REGISTERED->value);
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with($args);
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$this->usersServiceMock->expects($this->once())->method('loadDatatable');
		$this->usersServiceMock->expects($this->once())->method('getCurrentFilterResults')->willReturn([
			['UID' => 1, 'username' => 'JohnDoe'],
			['UID' => 2, 'username' => 'JaneDoe']
		]);

		// autocomplete expects the plain list, no success envelope
		$this->responseHandlerMock->expects($this->once())->method('jsonResponse')
			->with($this->responseStub, [
				['id' => 1, 'name' => 'JohnDoe'],
				['id' => 2, 'name' => 'JaneDoe']
			])
			->willReturn($this->responseStub);

		$result = $this->controller->findByName($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->responseStub, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testDeleteWithInvalidCsrfToken(): void
	{
		$this->expectUntouched($this->usersServiceMock, $this->usersAdminServiceMock, $this->parametersMock);
		$this->requestStub->method('getParsedBody')->willReturn(['UID' => 123, 'csrf_token' => 'wrong']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('wrong')->willReturn(false);
		$this->expectError('CSRF token mismatch.');

		static::assertSame($this->responseStub, $this->controller->delete($this->requestStub, $this->responseStub));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testDeleteWithoutUID(): void
	{
		$this->expectUntouched($this->usersServiceMock, $this->usersAdminServiceMock, $this->parametersMock);
		$this->requestStub->method('getParsedBody')->willReturn(['csrf_token' => 'token']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('token')->willReturn(true);
		$this->expectError('No UID');

		static::assertSame($this->responseStub, $this->controller->delete($this->requestStub, $this->responseStub));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testDeleteSucceeds(): void
	{
		$this->expectUntouched($this->usersServiceMock, $this->parametersMock);
		$this->requestStub->method('getParsedBody')->willReturn(['UID' => 123, 'csrf_token' => 'token']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('token')->willReturn(true);
		$this->usersAdminServiceMock->expects($this->once())->method('deleteUser')->with(123);
		$this->responseHandlerMock->expects($this->never())->method('jsonError');
		$this->responseHandlerMock->expects($this->once())->method('jsonSuccess')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		static::assertSame($this->responseStub, $this->controller->delete($this->requestStub, $this->responseStub));
	}

	/**
	 * Translatable errors of the service are not caught, the JsonErrorHandler answers them.
	 *
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Throwable
	 */
	#[Group('units')]
	public function testDeletePassesServiceExceptionThrough(): void
	{
		$this->expectUntouched($this->usersServiceMock, $this->parametersMock, $this->responseHandlerMock);
		$this->requestStub->method('getParsedBody')->willReturn(['UID' => 123, 'csrf_token' => 'token']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$exception = new AccessDeniedException('users', 'user_not_deletable', ['UID' => 123]);
		$this->usersAdminServiceMock->expects($this->once())->method('deleteUser')->with(123)->willThrowException($exception);

		$this->expectExceptionObject($exception);

		$this->controller->delete($this->requestStub, $this->responseStub);
	}

	private function expectError(string $message): void
	{
		$this->responseHandlerMock->expects($this->never())->method('jsonSuccess');
		$this->responseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseStub, $message)
			->willReturn($this->responseStub);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
