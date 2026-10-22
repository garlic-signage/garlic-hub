<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Account\Controller;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Account\Controller\ShowPasswordController;
use App\Modules\Account\Helper\Password\Orchestrator;
use App\Modules\Account\Helper\Password\TemplatePreparer;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

class ShowPasswordControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private Messages&MockObject $flashMock;
	private ShowPasswordController $controller;

	/** @var array{UID:int, company_id:int, username:string, status:int, purpose:string} */
	private array $tokenUser = ['UID' => 9, 'company_id' => 1, 'username' => 'heidi', 'status' => 1, 'purpose' => 'password_reset'];

	/** @var array{hidden: list<mixed>, visible: list<mixed>} */
	private array $form = ['hidden' => [], 'visible' => []];

	/** @var array<string,array<string,string>> */
	private array $templateData = ['main_layout' => ['LANG_PAGE_TITLE' => 'x']];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->templatePreparerMock = $this->createMock(TemplatePreparer::class);
		$this->flashMock            = $this->createMock(Messages::class);
		$translatorStub             = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key);

		$this->controller = new ShowPasswordController(
			$this->orchestratorMock,
			$this->templatePreparerMock,
			$translatorStub,
			$this->flashMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowPasswordForm(): void
	{
		$this->expectPasswordFormRendering();
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->showPasswordForm($this->createStub(ServerRequestInterface::class), new Response());

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowForcedPasswordFormWithoutToken(): void
	{
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'account.no_token');

		$response = $this->controller->showForcedPasswordForm($this->createQueryRequest([]), new Response());

		$this->assertRedirectedTo('/login', $response);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowForcedPasswordFormWithInvalidToken(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadTokenUser')->with('abcd')->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('buildForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'account.token_error');

		$response = $this->controller->showForcedPasswordForm($this->createQueryRequest(['token' => 'abcd']), new Response());

		$this->assertRedirectedTo('/login', $response);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowForcedPasswordForm(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadTokenUser')->with('abcd')->willReturn($this->tokenUser);
		$this->expectForcedFormRendering();
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->showForcedPasswordForm($this->createQueryRequest(['token' => 'abcd']), new Response());

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreForcedPasswordWithoutToken(): void
	{
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'account.no_token');

		$response = $this->controller->storeForcedPassword($this->createRequest(['password' => 'secret']), new Response());

		$this->assertRedirectedTo('/login', $response);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreForcedPasswordWithInvalidToken(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadTokenUser')->with('abcd')->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('storeForcedPassword');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'account.token_error');

		$response = $this->controller->storeForcedPassword($this->createRequest(['token' => 'abcd']), new Response());

		$this->assertRedirectedTo('/login', $response);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreForcedPasswordWithErrorsShowsFormAgain(): void
	{
		$post = ['token' => 'abcd', 'password' => ''];
		$this->orchestratorMock->expects($this->once())->method('loadTokenUser')->willReturn($this->tokenUser);
		$this->orchestratorMock->expects($this->once())->method('storeForcedPassword')->with(9, 'abcd', $post)
			->willReturn(['success' => false, 'errors' => ['No password.']]);
		$this->flashMock->expects($this->once())->method('addMessageNow')->with('error', 'No password.');
		$this->flashMock->expects($this->never())->method('addMessage');
		$this->expectForcedFormRendering();

		$response = $this->controller->storeForcedPassword($this->createRequest($post), new Response());

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreForcedPassword(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadTokenUser')->willReturn($this->tokenUser);
		$this->orchestratorMock->expects($this->once())->method('storeForcedPassword')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('buildForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'account.forced_password_changed');

		$response = $this->controller->storeForcedPassword($this->createRequest(['token' => 'abcd']), new Response());

		$this->assertRedirectedTo('/login', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreWithErrorsShowsFormAgain(): void
	{
		$post = ['password' => ''];
		$this->orchestratorMock->expects($this->once())->method('storePassword')->with($post)
			->willReturn(['success' => false, 'errors' => ['No password.', 'No confirm.']]);
		$errors = [];
		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnCallback(function (string $key, string $message) use (&$errors): void { $errors[] = $key . ':' . $message; });
		$this->flashMock->expects($this->never())->method('addMessage');
		$this->expectPasswordFormRendering();

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRendered($response);
		static::assertSame(['error:No password.', 'error:No confirm.'], $errors);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStore(): void
	{
		$this->orchestratorMock->expects($this->once())->method('storePassword')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('buildForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'account.password_changed');

		$response = $this->controller->store($this->createRequest(['password' => 'secret']), new Response());

		$this->assertRedirectedTo('/', $response);
	}

	private function expectPasswordFormRendering(): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildForm')->with()->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('preparePasswordForm')->with($this->form)
			->willReturn($this->templateData);
	}

	private function expectForcedFormRendering(): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildForm')->with('abcd')->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepareForcedPasswordForm')
			->with($this->form, $this->tokenUser)->willReturn($this->templateData);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function createRequest(array $post): ServerRequestInterface
	{
		$request = $this->createStub(ServerRequestInterface::class);
		$request->method('getParsedBody')->willReturn($post);

		return $request;
	}

	/**
	 * @param array<string,string> $query
	 */
	private function createQueryRequest(array $query): ServerRequestInterface
	{
		$request = $this->createStub(ServerRequestInterface::class);
		$request->method('getQueryParams')->willReturn($query);

		return $request;
	}

	private function assertRendered(ResponseInterface $response): void
	{
		static::assertSame(200, $response->getStatusCode());
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame(serialize($this->templateData), (string) $response->getBody());
	}

	private function assertRedirectedTo(string $location, ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
	}
}
