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
use App\Framework\Core\BaseValidator;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Users\Controller\ShowDatatableController;
use App\Modules\Users\Helper\Datatable\DatatableFacade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowDatatableControllerTest extends TestCase
{
	private ShowDatatableController $controller;
	private DatatableFacade&MockObject $facadeMock;
	private DatatableTemplatePreparer&MockObject $templatePreparerMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private Translator&MockObject $translatorMock;
	private Messages&MockObject $flashMock;
	private Session&MockObject $sessionMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private BaseValidator&Stub $validatorStub;
	private JsonResponseHandler&MockObject $responseHandlerMock;


	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->facadeMock           = $this->createMock(DatatableFacade::class);
		$this->templatePreparerMock = $this->createMock(DatatableTemplatePreparer::class);
		$this->requestMock          = $this->createMock(ServerRequestInterface::class);
		$this->responseMock         = $this->createMock(ResponseInterface::class);
		$this->translatorMock       = $this->createMock(Translator::class);
		$this->flashMock            = $this->createMock(Messages::class);
		$this->sessionMock          = $this->createMock(Session::class);
		$this->streamInterfaceMock  = $this->createMock(StreamInterface::class);

		$this->validatorStub        = $this->createStub(BaseValidator::class);
		$this->responseHandlerMock  = $this->createMock(JsonResponseHandler::class);

		$this->controller = new ShowDatatableController(
			$this->facadeMock,
			$this->templatePreparerMock,
			$this->validatorStub,
			$this->responseHandlerMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShowMethodReturnsResponseWithSerializedTemplateData(): void
	{
		$this->expectUntouched($this->translatorMock, $this->flashMock, $this->sessionMock, $this->responseHandlerMock);
		$templateData = ['key' => 'value'];

		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['translator', null, $this->translatorMock],
				['session', null, $this->sessionMock],
			]);

		$this->facadeMock->expects($this->once())->method('configure')
			->with($this->translatorMock, $this->sessionMock);

		$this->requestMock->method('getQueryParams')->willReturn(['page' => 2]);
		$this->facadeMock->expects($this->once())->method('processSubmittedUserInput')->with(['page' => 2]);
		$this->facadeMock->expects($this->once())->method('prepareDataGrid');
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->willReturn(['dataGrid' => 'value']);

		$this->templatePreparerMock->expects($this->once())->method('preparerUITemplate')
			->with(['dataGrid' => 'value'])
			->willReturn($templateData);

		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);

		$this->streamInterfaceMock->expects($this->once())->method('write')
			->with(serialize($templateData));

		$this->responseMock
			->expects($this->once())
			->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$result = $this->controller->show($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteWithInvalidCsrfToken(): void
	{
		$this->expectUntouched($this->templatePreparerMock, $this->responseMock, $this->streamInterfaceMock);
		$this->requestMock->expects($this->atLeastOnce())->method('getParsedBody')->willReturn(['UID' => 123, 'csrf_token' => 'wrong']);
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->setStandardMocks(123);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('csrf_token_mismatch', 'security')
			->willReturn('CSRF token mismatch');
		$this->facadeMock->expects($this->never())->method('deleteUser');
		$this->expectError('CSRF token mismatch');

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteNoUser(): void
	{
		$this->expectUntouched($this->templatePreparerMock, $this->responseMock, $this->streamInterfaceMock);
		$this->requestMock->expects($this->atLeastOnce())->method('getParsedBody')->willReturn(['csrf_token' => 'token']);
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->setStandardMocks(0);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('user_not_found', 'users')
			->willReturn('User not found');
		$this->facadeMock->expects($this->never())->method('deleteUser');
		$this->expectError('User not found');

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteMethodHandlesSuccessfulDeletion(): void
	{
		$this->expectUntouched($this->templatePreparerMock, $this->responseMock, $this->streamInterfaceMock);
		$this->requestMock->expects($this->atLeastOnce())->method('getParsedBody')->willReturn(['UID' => 123, 'csrf_token' => 'token']);
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->setStandardMocks(123);

		$this->facadeMock->expects($this->once())->method('configure')
			->with($this->translatorMock, $this->sessionMock);
		$this->facadeMock->expects($this->once())->method('deleteUser')
			->with(123)
			->willReturn(true);
		$this->translatorMock->expects($this->once())->method('translate')
			->with('user_deleted', 'users')
			->willReturn('User deleted');
		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'User deleted');
		$this->responseHandlerMock->expects($this->never())->method('jsonError');
		$this->responseHandlerMock->expects($this->once())->method('jsonSuccess')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$result = $this->controller->delete($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteFails(): void
	{
		$this->expectUntouched($this->templatePreparerMock, $this->responseMock, $this->streamInterfaceMock);
		$this->requestMock->expects($this->atLeastOnce())->method('getParsedBody')->willReturn(['UID' => 123, 'csrf_token' => 'token']);
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->setStandardMocks(123);

		$this->facadeMock->expects($this->once())->method('configure')
			->with($this->translatorMock, $this->sessionMock);
		$this->facadeMock->expects($this->once())->method('deleteUser')
			->with(123)
			->willReturn(false);
		$this->translatorMock->expects($this->once())->method('translate')
			->with('user_delete_failed', 'users')
			->willReturn('User delete failed.');
		$this->expectError('User delete failed.');

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	private function setStandardMocks(int $UID): void
	{
		$this->requestMock->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
				['session', null, $this->sessionMock]
			]);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => $UID]);
	}

	/**
	 * The error goes to the flash messages for the reload and into the JSON response.
	 */
	private function expectError(string $message): void
	{
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', $message);
		$this->responseHandlerMock->expects($this->never())->method('jsonSuccess');
		$this->responseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseMock, $message)
			->willReturn($this->responseMock);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
