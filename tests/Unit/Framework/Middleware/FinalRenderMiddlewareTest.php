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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Config\Config;
use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Middleware\FinalRenderMiddleware;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Modules\Users\Services\AclValidator;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Random\RandomException;
use Slim\Flash\Messages;

class FinalRenderMiddlewareTest extends TestCase
{
	use PHPMock;

	private FinalRenderMiddleware $middleware;
	private AclValidator&MockObject $aclValidatorMock;
	private AdapterInterface&MockObject $templateServiceMock;
	private ServerRequestInterface&Stub $requestStub ;
	private ResponseInterface&MockObject $responseMock;
	private RequestHandlerInterface&Stub $handlerStub;
	private Translator&MockObject $translatorMock;
	private Session&MockObject $sessionMock;
	private Config&MockObject $configMock;
	private Locales&Stub $localesStub;
	private Messages&MockObject $flashMock;

	/**
	 * @throws \Exception|Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->templateServiceMock = $this->createMock(AdapterInterface::class);
		$this->aclValidatorMock    = $this->createMock(AclValidator::class);
		$this->requestStub         = $this->createStub(ServerRequestInterface::class);
		$this->responseMock        = $this->createMock(ResponseInterface::class);
		$this->handlerStub         = $this->createStub(RequestHandlerInterface::class);
		$uriInterfaceStub = $this->createStub(UriInterface::class);
		$this->translatorMock      = $this->createMock(Translator::class);
		$this->sessionMock         = $this->createMock(Session::class);
		$this->localesStub         = $this->createStub(Locales::class);
		$this->configMock          = $this->createMock(Config::class);
		$this->flashMock 	       = $this->createMock(Messages::class);
		$this->requestStub->method('getUri')->willReturn($uriInterfaceStub);
		$csrfTokenStub = $this->createStub(CsrfToken::class);
		$this->middleware = new FinalRenderMiddleware($this->templateServiceMock, $this->aclValidatorMock, $csrfTokenStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws RandomException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessReturnsControllerFalse(): void
	{
		$_ENV['APP_DEBUG'] = false;
		$this->executeStandardMocks();

		// no need for layoutData here

		$responseBodyMock = $this->createStub(StreamInterface::class);
		$this->responseMock->method('getBody')->willReturn($responseBodyMock);
		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturn($this->responseMock);
		$this->templateServiceMock->expects($this->never())->method('render');

		$this->middleware->process($this->requestStub, $this->handlerStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws RandomException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessReturnsHtmlWithControllerData(): void
	{
		$_ENV['APP_DEBUG'] = false;
		$this->executeStandardMocks();

		// no need for layoutData here

		$controllerData = serialize([
			'this_layout' => [
				'template' => 'content',
				'data' => ['key' => 'value']
			],
			'main_layout' => ['title' => 'Test Title']
		]);

		$responseBodyMock = $this->createMock(StreamInterface::class);
		$responseBodyMock->method('__toString')->willReturn($controllerData);
		$this->responseMock->method('getBody')->willReturn($responseBodyMock);

		$this->templateServiceMock->expects($this->exactly(2))->method('render')
		  ->willReturnOnConsecutiveCalls('Rendered Content','Final Rendered Page');

		$this->responseMock->expects($this->once())->method('withBody')->willReturn($this->responseMock);

		$responseBodyMock->expects($this->once())->method('write')
						->with('Final Rendered Page')
		;
		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturn($this->responseMock);

		$this->middleware->process($this->requestStub, $this->handlerStub);

	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws RandomException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testProcessFailFOpen(): void
	{
		$_ENV['APP_DEBUG'] = false;
		$this->executeStandardMocks();

		// no need for layoutData here

		$controllerData = serialize([
			'this_layout' => [
				'template' => 'content',
				'data' => ['key' => 'value']
			],
			'main_layout' => ['title' => 'Test Title']
		]);

		$fopen = $this->getFunctionMock('App\Framework\Middleware', 'fopen');
		$fopen->expects($this->once())->willReturn(false);

		$responseBodyMock = $this->createMock(StreamInterface::class);
		$responseBodyMock->method('__toString')->willReturn($controllerData);
		$this->responseMock->method('getBody')->willReturn($responseBodyMock);

		$this->templateServiceMock->expects($this->exactly(2))->method('render')
			->willReturnOnConsecutiveCalls('Rendered Content','Final Rendered Page');

		$this->responseMock->expects($this->never())->method('withBody');
		$responseBodyMock->expects($this->never())->method('write');

		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturn($this->responseMock);

		$this->middleware->process($this->requestStub, $this->handlerStub);

	}


	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws RandomException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessHandlesDebugMode(): void
	{
		$_ENV['APP_DEBUG'] = true;

		$this->executeStandardMocks();

		// no need for layoutData here

		$responseBodyMock = $this->createStub(StreamInterface::class);
		$this->responseMock->method('getBody')->willReturn($responseBodyMock);
		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturn($this->responseMock);
		$this->templateServiceMock->expects($this->never())->method('render');

		$this->middleware->process($this->requestStub, $this->handlerStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws RandomException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessWithMessage(): void
	{
		$_ENV['APP_DEBUG'] = false;
		$this->executeStandardMocks(true);

		$this->flashMock->expects($this->exactly(2))->method('getMessage')
			->willReturnCallback(function ($param)
			{
				return match ($param)
				{
					'error' => ['Error message'],
					'success' => ['Success message'],
					default => null,
				};
			});

		$controllerData = serialize([
			'this_layout' => [
				'template' => 'content',
				'data' => ['key' => 'value']
			],
			'main_layout' => ['title' => 'Test Title']
		]);

		$responseBodyMock = $this->createMock(StreamInterface::class);
		$responseBodyMock->method('__toString')->willReturn($controllerData);
		$this->responseMock->method('getBody')->willReturn($responseBodyMock);

		$this->templateServiceMock->expects($this->exactly(2))->method('render')
			->willReturnOnConsecutiveCalls('Rendered Content','Final Rendered Page');

		$this->responseMock->expects($this->once())->method('withBody')->willReturn($this->responseMock);

		$responseBodyMock->expects($this->once())->method('write')	->with('Final Rendered Page');
		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturn($this->responseMock);

		$this->middleware->process($this->requestStub, $this->handlerStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws RandomException
	 */
	#[Group('units')]
	public function testProcessWithUser(): void
	{
		$_ENV['APP_DEBUG'] = false;
		$this->executeStandardMocks(false, true);

		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 8]);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('isModuleAdmin')->with(8)->willReturn(false);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('isSubAdmin')->with(8)->willReturn(true);
		$controllerData = serialize([
			'this_layout' => [
				'template' => 'content',
				'data' => ['key' => 'value']
			],
			'main_layout' => ['title' => 'Test Title']
		]);

		$responseBodyMock = $this->createMock(StreamInterface::class);
		$responseBodyMock->method('__toString')->willReturn($controllerData);
		$this->responseMock->method('getBody')->willReturn($responseBodyMock);

		$this->templateServiceMock->expects($this->exactly(2))->method('render')
			->willReturnOnConsecutiveCalls('Rendered Content','Final Rendered Page');

		$this->responseMock->expects($this->once())->method('withBody')->willReturn($this->responseMock);

		$responseBodyMock->expects($this->once())->method('write')	->with('Final Rendered Page');
		$this->responseMock->method('withHeader')->with('Content-Type', 'text/html')->willReturn($this->responseMock);

		$this->middleware->process($this->requestStub, $this->handlerStub);

	}


	private function executeStandardMocks(bool $hasMessages = false, bool $hasUser = false): void
	{
		$this->handlerStub->method('handle')->willReturn($this->responseMock);
		$this->requestStub
			->method('getAttribute')
			->willReturnCallback(function ($attribute)
			{
				if ($attribute === 'session')
					return $this->sessionMock;
				elseif ($attribute === 'translator')
					return $this->translatorMock;
				elseif ($attribute === 'locales')
					return $this->localesStub;
				elseif ($attribute === 'config')
					return $this->configMock;
				elseif ($attribute === 'flash')
					return $this->flashMock;

				return null;
			});
		// both message types are always checked
		$this->flashMock->expects($this->exactly(2))->method('hasMessage')
			->willReturnMap([['error', $hasMessages], ['success', $hasMessages]]);
		if (!$hasMessages)
			$this->flashMock->expects($this->never())->method('getMessage');
		// the admin menu needs ACL checks only for a logged-in user
		if (!$hasUser)
			$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->sessionMock->expects($this->any())->method('exists')->with('user')->willReturn($hasUser);
		$this->translatorMock->method('translate')->willReturn('translate-dummy');

		$this->translatorMock->method('translateArrayForOptions')
			->with('languages', 'menu')
			->willReturn(['en_US' => 'english', 'de_DE' => 'german']);
		$availableLanguages = ['en_US', 'de_DE'];
		$this->localesStub->method('getLanguageCode')->willReturn('en');

		$this->localesStub->method('getAvailableLocales')->willReturn($availableLanguages);


		$this->configMock->method('getEnv')->with('APP_NAME')->willReturn('edge');
	}


}
