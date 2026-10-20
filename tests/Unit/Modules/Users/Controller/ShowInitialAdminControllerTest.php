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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Users\Controller\ShowInitialAdminController;
use App\Modules\Users\Helper\InitialAdmin\Facade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowInitialAdminControllerTest extends TestCase
{
	private Facade&MockObject $facadeMock;
	private FormTemplatePreparer&MockObject $formElementPreparerMock;
	private Messages&MockObject $flashMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private ShowInitialAdminController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->facadeMock = $this->createMock(Facade::class);
		$this->formElementPreparerMock = $this->createMock(FormTemplatePreparer::class);
		$this->flashMock = $this->createMock(Messages::class);

		$this->requestMock         = $this->createMock(ServerRequestInterface::class);
		$this->responseMock        = $this->createMock(ResponseInterface::class);
		$this->streamInterfaceMock = $this->createMock(StreamInterface::class);

		$this->controller = new ShowInitialAdminController($this->facadeMock, $this->formElementPreparerMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShowFunctionIsNotAllowed(): void
	{
		$this->expectUntouched($this->formElementPreparerMock, $this->flashMock, $this->requestMock, $this->streamInterfaceMock);
		$this->facadeMock->expects($this->once())->method('isFunctionAllowed')
			->willReturn(false);

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$response = $this->controller->show($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testShowReturnsRenderedFormOnAllowedFunction(): void
	{
		$this->flashMock->expects($this->never())->method($this->anything());
		$this->facadeMock->expects($this->once())->method('isFunctionAllowed')
			->willReturn(true);

		$this->setStandardMocks();
		$this->outputStandard([]);

		$this->controller->show($this->requestMock, $this->responseMock);

	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreFunctionIsNotAllowed(): void
	{
		$this->expectUntouched($this->formElementPreparerMock, $this->flashMock, $this->requestMock, $this->streamInterfaceMock);
		$this->facadeMock->expects($this->once())->method('isFunctionAllowed')
			->willReturn(false);

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$response = $this->controller->store($this->requestMock, $this->responseMock);
		static::assertSame($this->responseMock, $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreAddsErrorMessagesAndReturnsRenderedFormOnValidationErrors(): void
	{
		$this->facadeMock->expects($this->once())->method('isFunctionAllowed')
			->willReturn(true);

		$postData = ['username' => '', 'email' => '', 'locale' => '', 'password' => '', 'password_confirm' => ''];

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn(['Validation error']);

		$this->flashMock->expects($this->once())->method('addMessageNow')
			->with('error', 'Validation error');

		$this->outputStandard($postData);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreRedirectsToLoginOnSuccessfulUserStore(): void
	{
		$this->formElementPreparerMock->expects($this->never())->method($this->anything());
		$this->streamInterfaceMock->expects($this->never())->method($this->anything());
		$this->facadeMock->expects($this->once())->method('isFunctionAllowed')
			->willReturn(true);

		$postData = [
			'username' => 'admin',
			'email' => 'admin@example.com',
			'locale' => 'en',
			'password' => 'password123',
			'password_confirm' => 'password123',
		];

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storeUser')
			->willReturn(1);

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'Admin User “admin“ successfully stored. You can now login with your username and password.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/login')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$response = $this->controller->store($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreAddsErrorMessagesAndReturnsRenderedFormOnUserServiceErrors(): void
	{
		$this->facadeMock->expects($this->once())->method('isFunctionAllowed')
			->willReturn(true);

		$postData = [
			'username' => 'admin',
			'email' => 'admin@example.com',
			'locale' => 'en',
			'password' => 'password123',
			'password_confirm' => 'password123',
		];

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storeUser')
			->willReturn(0);

		$this->facadeMock->expects($this->once())->method('getUserServiceErrors')
			->willReturn(['User service error']);

		$this->flashMock->expects($this->once())->method('addMessageNow')
			->with('error', 'User service error');

		$this->outputStandard($postData);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	private function setStandardMocks(): void
	{
		$translatorStub = $this->createStub(Translator::class);
		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $translatorStub],
			]);

		$this->facadeMock->expects($this->once())->method('init')->with($translatorStub);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function outputStandard(array $data): void
	{
		$dataSections = ['key' => 'value'];
		$templateData = ['main_layout' => ['key' => 'value'], 'this_layout' => ['key2' => 'value2']];
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with($data)
			->willReturn($dataSections);

		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')
			->with($dataSections)
			->willReturn($templateData);
		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->method('write')->with(serialize($templateData));
		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(200);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
