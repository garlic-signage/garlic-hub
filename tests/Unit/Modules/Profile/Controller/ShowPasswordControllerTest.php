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


namespace Tests\Unit\Modules\Profile\Controller;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Profile\Controller\ShowPasswordController;
use App\Modules\Profile\Helper\Password\Facade;
use DateMalformedStringException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Random\RandomException;
use Slim\Flash\Messages;

class ShowPasswordControllerTest extends TestCase
{
	private Facade&MockObject $facadeMock;
	private FormTemplatePreparer&MockObject $formElementPreparerMock;
	private Messages&MockObject $flashMock;
	private Translator&MockObject $translatorMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private ShowPasswordController $controller;
	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->facadeMock              = $this->createMock(Facade::class);
		$this->formElementPreparerMock = $this->createMock(FormTemplatePreparer::class);
		$this->flashMock               = $this->createMock(Messages::class);
		$this->translatorMock          = $this->createMock(Translator::class);

		$this->requestMock         = $this->createMock(ServerRequestInterface::class);
		$this->responseMock        = $this->createMock(ResponseInterface::class);
		$this->streamInterfaceMock = $this->createMock(StreamInterface::class);

		$this->controller = new ShowPasswordController($this->facadeMock, $this->formElementPreparerMock);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowPasswordFormReturnsExpectedResponse(): void
	{
		$this->setStandardMocks();

		$dataSections = ['key' => 'value'];
		$this->outputStandard($dataSections, '');

		$this->controller->showPasswordForm($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowForcedPasswordFormRedirectsWhenTokenIsMissing(): void
	{
		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getQueryParams')
			->willReturn([]);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('no_token', 'profile')
			->willReturn('Translated no token message.');

		$this->flashMock->expects($this->once())
			->method('addMessageNow')
			->with('error', 'Translated no token message.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/login')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302);

		$this->controller->showForcedPasswordForm($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 * @throws RandomException
	 */
	#[Group('units')]
	public function testShowForcedPasswordFormRedirectsWhenUIDIsInvalid(): void
	{
		$passwordToken = bin2hex(random_bytes(32));

		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getQueryParams')
			->willReturn(['token' => $passwordToken]);

		$this->facadeMock->expects($this->once())->method('determineUIDByToken')
			->willReturn(0);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('token_error', 'profile')
			->willReturn('Translated token error.');

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('error', 'Translated token error.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/login')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302);

		$this->controller->showForcedPasswordForm($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 * @throws RandomException
	 */
	#[Group('units')]
	public function testShowForcedPasswordFormRendersFormSuccessfully(): void
	{
		$passwordToken = bin2hex(random_bytes(32));

		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getQueryParams')
			->willReturn(['token' => $passwordToken]);

		$this->facadeMock->expects($this->once())->method('determineUIDByToken')
			->with(hex2bin($passwordToken))
			->willReturn(123);

		$dataSections = ['key' => 'value'];
		$templateData = ['main_layout' => ['key' => 'value'], 'this_layout' => ['key2' => 'value2']];

		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with($passwordToken)
			->willReturn($dataSections);

		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')
			->with($dataSections)
			->willReturn($templateData);

		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->expects($this->once())->method('write')->with(serialize($templateData));

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(200);

		$this->controller->showForcedPasswordForm($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws DatabaseException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordRedirectsWhenTokenIsMissing(): void
	{
		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn([]);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('no_token', 'profile')
			->willReturn('Translated no token message.');

		$this->flashMock->expects($this->once())
			->method('addMessageNow')
			->with('error', 'Translated no token message.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/login')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302);

		$this->controller->storeForcedPassword($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws DatabaseException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordRedirectsWhenUIDIsInvalid(): void
	{
		$passwordToken = 'invalidToken';

		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn(['token' => $passwordToken]);

		$this->facadeMock->expects($this->once())->method('determineUIDByToken')
			->with($passwordToken)
			->willReturn(0);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('token_error', 'profile')
			->willReturn('Translated token error.');

		$this->flashMock->expects($this->once())
			->method('addMessageNow')
			->with('error', 'Translated token error.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/login')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302);

		$this->controller->storeForcedPassword($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws DatabaseException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordHandlesValidationErrors(): void
	{
		$postData = ['token' => 'validToken', 'password' => 'short'];
		$passwordToken = 'validToken';
		$errors = ['Password is too short.'];

		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->facadeMock->expects($this->once())->method('determineUIDByToken')
			->with($passwordToken)
			->willReturn(123);

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn($errors);

		$this->flashMock->expects($this->exactly(1))
			->method('addMessageNow')
			->with('error', $errors[0]);

		$this->outputStandard($postData, $passwordToken);

		$this->controller->storeForcedPassword($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws DatabaseException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordStoresSuccessfulPassword(): void
	{
		$postData = ['token' => 'validToken', 'password' => 'strongPassword123!'];
		$passwordToken = 'validToken';

		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->facadeMock->expects($this->once())->method('determineUIDByToken')
			->with($passwordToken)
			->willReturn(123);

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storeForcedPassword')
			->with(123, $passwordToken)
			->willReturn(123);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('forced_password_changed', 'profile')
			->willReturn('Password change success.');

		$this->flashMock->expects($this->once())
			->method('addMessage')
			->with('success', 'Password change success.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/login')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302);

		$this->controller->storeForcedPassword($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws DatabaseException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordHandlesSavingErrors(): void
	{
		$postData = ['token' => 'validToken', 'password' => 'strongPassword123!'];
		$passwordToken = 'validToken';
		$savingErrors = ['An unexpected error occurred.'];

		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
			]);

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->facadeMock->expects($this->once())->method('determineUIDByToken')
			->with($passwordToken)
			->willReturn(123);

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storeForcedPassword')
			->with(123, $passwordToken)
			->willReturn(0);

		$this->facadeMock->expects($this->once())->method('getUserServiceErrors')
			->willReturn($savingErrors);

		$this->flashMock->expects($this->once())
			->method('addMessageNow')
			->with('error', $savingErrors[0]);
		$this->outputStandard($postData, $passwordToken);

		$this->controller->storeForcedPassword($this->requestMock, $this->responseMock);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreSuccessful(): void
	{
		$postData = ['password' => 'strongPassword123!'];
		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storePassword')
			->willReturn(123);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('password_changed', 'profile', [])
			->willReturn('Password change successful.');

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'Password change successful.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreValidationErrors(): void
	{
		$postData = ['password' => 'weakPassword'];
		$errors = ['Password is too weak.'];
		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->setStandardMocks();

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn($errors);

		$this->outputStandard($errors, '');

		$this->responseMock->method('getBody')->willReturn($this->streamInterfaceMock);

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreSaveError(): void
	{
		$postData = ['password' => 'strongPassword123!'];
		$savingErrors = ['An error occurred while saving the password.'];

		$this->setStandardMocks();

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($postData);

		$this->facadeMock->expects($this->once())->method('configureUserFormParameter')
			->with($postData)
			->willReturn([]);

		$this->facadeMock->expects($this->once())->method('storePassword')
			->willReturn(0);

		$this->facadeMock->expects($this->once())->method('getUserServiceErrors')
			->willReturn($savingErrors);

		$this->flashMock->expects($this->once())
			->method('addMessageNow')
			->with('error', $savingErrors[0]);

		$this->outputStandard($postData, '');

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	private function setStandardMocks(): void
	{
		$sessionMock    = $this->createMock(Session::class);
		$this->requestMock->expects($this->exactly(3))->method('getAttribute')
			->willReturnMap([
				['flash', null, $this->flashMock],
				['translator', null, $this->translatorMock],
				['session', null, $sessionMock],
			]);

		$this->facadeMock->expects($this->once())->method('init')
			->with($sessionMock);
	}

	/**
	 * @param array<string|int,string> $dataSections
	 */
	private function outputStandard(array $dataSections, string $passwordToken): void
	{
		$templateData = ['main_layout' => ['key' => 'value'], 'this_layout' => ['key2' => 'value2']];
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with($passwordToken)
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
