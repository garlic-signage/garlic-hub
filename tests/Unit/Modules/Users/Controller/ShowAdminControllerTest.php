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

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Users\Controller\ShowAdminController;
use App\Modules\Users\Helper\Settings\Facade;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowAdminControllerTest extends TestCase
{
	private Facade&MockObject $facadeMock;
	private FormTemplatePreparer&MockObject $formElementPreparerMock;
	private Messages&MockObject $flashMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private ShowAdminController $controller;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
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

		$this->controller = new ShowAdminController($this->facadeMock, $this->formElementPreparerMock);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testNewUserFormSuccessful(): void
	{
		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('buildCreateNewParameter');
		$this->outputStandard([]);

		$this->controller->newUserForm($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testEditUserFormSuccessful(): void
	{
		$this->setStandardMocks();

		$userData = ['UID' => 1, 'username' => 'testuser'];
		$args = ['UID' => 1];

		$this->facadeMock->expects($this->once())->method('loadUserForEdit')
			->with(1)
			->willReturn($userData);
		$this->facadeMock->expects($this->once())->method('buildEditParameter');
		$this->outputStandard($userData);

		$this->controller->editUserForm($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testEditUserFormInvalidUIDRedirectsToUsers(): void
	{
		$this->setStandardMocks();

		$args = ['UID' => 0];
		$this->flashMock->expects($this->once())->method('addMessage')
			->with('error', 'UID not valid.');

		$this->responseMock->method('withHeader')->with('Location', '/users')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(302);

		$this->controller->editUserForm($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testEditUserFormUserNotFoundRedirectsToUsers(): void
	{
		$this->setStandardMocks();

		$args = ['UID' => 999];
		$this->facadeMock->expects($this->once())->method('loadUserForEdit')->with(999)->willReturn([]);
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'User not found.');
		$this->responseMock->method('withHeader')->with('Location', '/users')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(302);

		$this->controller->editUserForm($this->requestMock, $this->responseMock, $args);

	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStoreWithValidationErrors(): void
	{
		$this->setStandardMocks();

		$postData = ['username' => '', 'password' => ''];
		$errors = ['Username cannot be empty.', 'Password cannot be empty.'];

		$this->requestMock->method('getParsedBody')->willReturn($postData);
		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn($errors);

		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnMap([
				['error', 'Username cannot be empty.'],
				['error', 'Password cannot be empty.']
			]);

		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->expects($this->once())->method('write');

		$this->outputStandard($postData);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStoreUserSuccessfully(): void
	{
		$this->setStandardMocks();

		$postData = ['UID' => '1', 'username' => 'JohnDoe', 'standardSubmit' => true];
		$this->requestMock->method('getParsedBody')->willReturn($postData);

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')->with($postData)->willReturn([]);
		$this->facadeMock->expects($this->once())->method('storeUser')->with(1)->willReturn(1);

		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'User “JohnDoe“ successfully stored.');
		$this->responseMock->method('withHeader')->with('Location', '/users')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(302);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStorePasswordResetSuccessfully(): void
	{
		$this->setStandardMocks();

		$postData = ['UID' => '1', 'username' => 'JohnDoe', 'resetPassword' => true];
		$this->requestMock->method('getParsedBody')->willReturn($postData);

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')->with($postData)->willReturn([]);
		$this->facadeMock->expects($this->once())->method('createPasswordResetToken')->with(1)->willReturn('token123');

		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'User “JohnDoe“ Password reset was successfully.');
		$this->responseMock->method('withHeader')->with('Location', '/users/edit/1')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(302);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStoreServiceErrorsWhenStoringUser(): void
	{
		$this->setStandardMocks();

		$postData = ['UID' => '1', 'username' => 'JohnDoe', 'standardSubmit' => true];
		$serviceErrors = ['Unable to store user.'];

		$this->requestMock->method('getParsedBody')->willReturn($postData);
		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')->with($postData)->willReturn([]);
		$this->facadeMock->expects($this->once())->method('storeUser')->with(1)->willReturn(0);
		$this->facadeMock->expects($this->once())->method('getUserServiceErrors')->willReturn($serviceErrors);

		foreach ($serviceErrors as $errorText)
		{
			$this->flashMock->expects($this->once())->method('addMessageNow')->with('error', $errorText);
		}

		$this->outputStandard($postData);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testStoreServiceErrorsWhenResettingPassword(): void
	{
		$this->setStandardMocks();

		$postData = ['UID' => '1', 'username' => 'JohnDoe', 'resetPassword' => true];
		$serviceErrors = ['Failed to generate password reset token.'];

		$this->requestMock->method('getParsedBody')->willReturn($postData);
		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')->with($postData)->willReturn([]);
		$this->facadeMock->expects($this->once())->method('createPasswordResetToken')->with(1)->willReturn('');
		$this->facadeMock->expects($this->once())->method('getUserServiceErrors')->willReturn($serviceErrors);

		foreach ($serviceErrors as $errorText)
		{
			$this->flashMock->expects($this->once())->method('addMessage')->with('error', $errorText);
		}

		$this->responseMock->method('withHeader')->with('Location', '/users/edit/1')->willReturnSelf();
		$this->responseMock->method('withStatus')->with(302);

		$this->controller->store($this->requestMock, $this->responseMock);
	}


	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	private function setStandardMocks(): void
	{
		$translatorMock = $this->createMock(Translator::class);
		$sessionMock    = $this->createMock(Session::class);
		$this->requestMock->expects($this->exactly(3))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $translatorMock],
				['session', null, $sessionMock],
			]);

		$this->facadeMock->expects($this->once())->method('init')
			->with($translatorMock, $sessionMock);
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

}
