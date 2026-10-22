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

namespace Tests\Unit\Modules\Users\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Users\Controller\ShowAdminController;
use App\Modules\Users\Helper\Settings\Orchestrator;
use App\Modules\Users\Helper\Settings\TemplatePreparer;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

class ShowAdminControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private Messages&MockObject $flashMock;
	private ShowAdminController $controller;

	/** @var array{UID: int, company_id: int, status: int, locale: string, email:string, username:string, tokens: array{}} */
	private array $user = ['UID' => 12, 'company_id' => 1, 'status' => 1, 'locale' => 'de_DE',
		'email' => 'heidi@example.com', 'username' => 'heidi', 'tokens' => []];

	/** @var array{hidden: list<mixed>, visible: list<mixed>} */
	private array $form = ['hidden' => [], 'visible' => []];

	/** @var array{main_layout: array<string,string>, this_layout: array{template: string, data: array<string,string>}} */
	private array $templateData = ['main_layout' => ['LANG_PAGE_TITLE' => 'x'], 'this_layout' => ['template' => 'test/page', 'data' => ['key' => 'value']]];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->templatePreparerMock = $this->createMock(TemplatePreparer::class);
		$this->flashMock            = $this->createMock(Messages::class);

		$this->controller = new ShowAdminController($this->orchestratorMock, $this->templatePreparerMock, $this->flashMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testNewUserForm(): void
	{
		$this->expectCreateRendering([]);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->newUserForm($this->createStub(ServerRequestInterface::class), new Response());

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditUserFormWithoutUID(): void
	{
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'UID not valid.');

		$response = $this->controller->editUserForm($this->createStub(ServerRequestInterface::class), new Response(), []);

		$this->assertRedirectedTo('/users', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditUserFormUserNotFound(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadUserForEdit')->with(12)->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('buildEditForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'User not found.');

		$response = $this->controller->editUserForm($this->createStub(ServerRequestInterface::class), new Response(), ['UID' => '12']);

		$this->assertRedirectedTo('/users', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditUserForm(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadUserForEdit')->with(12)->willReturn($this->user);
		$this->expectEditRendering([]);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->editUserForm($this->createStub(ServerRequestInterface::class), new Response(), ['UID' => '12']);

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreInsertWithErrorsShowsFormAgain(): void
	{
		$post = ['username' => '', 'standardSubmit' => ''];
		$this->orchestratorMock->expects($this->never())->method('loadUserForEdit');
		$this->orchestratorMock->expects($this->once())->method('storeCreateSettings')->with($post)
			->willReturn(['success' => false, 'errors' => ['No username.', 'No email.']]);
		$errors = [];
		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnCallback(function (string $key, string $message) use (&$errors): void { $errors[] = $key . ':' . $message; });
		$this->flashMock->expects($this->never())->method('addMessage');
		$this->expectCreateRendering($post);

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRendered($response);
		static::assertSame(['error:No username.', 'error:No email.'], $errors);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreInsert(): void
	{
		$this->orchestratorMock->expects($this->once())->method('storeCreateSettings')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('buildCreateForm');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'User “heidi“ successfully stored.');

		$response = $this->controller->store($this->createRequest(['username' => 'heidi', 'standardSubmit' => '']), new Response());

		$this->assertRedirectedTo('/users', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreUpdateUserNotFound(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadUserForEdit')->with(12)->willReturn([]);
		$this->orchestratorMock->expects($this->never())->method('storeEditSettings');
		$this->orchestratorMock->expects($this->never())->method('resetPassword');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'User not found.');

		$response = $this->controller->store($this->createRequest(['UID' => '12', 'standardSubmit' => '']), new Response());

		$this->assertRedirectedTo('/users', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreUpdateWithErrorsShowsFormAgain(): void
	{
		$post = ['UID' => '12', 'username' => '', 'standardSubmit' => ''];
		$this->orchestratorMock->expects($this->once())->method('loadUserForEdit')->with(12)->willReturn($this->user);
		$this->orchestratorMock->expects($this->once())->method('storeEditSettings')->with(12, $post)
			->willReturn(['success' => false, 'errors' => ['No username.']]);
		$this->flashMock->expects($this->once())->method('addMessageNow')->with('error', 'No username.');
		$this->flashMock->expects($this->never())->method('addMessage');
		$this->expectEditRendering($post);

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreUpdate(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadUserForEdit')->willReturn($this->user);
		$this->orchestratorMock->expects($this->once())->method('storeEditSettings')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('resetPassword');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'User “heidi“ successfully stored.');

		$response = $this->controller->store($this->createRequest(['UID' => '12', 'username' => 'heidi', 'standardSubmit' => '']), new Response());

		$this->assertRedirectedTo('/users', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreResetPasswordWithErrors(): void
	{
		$post = ['UID' => '12', 'username' => 'heidi', 'resetPassword' => ''];
		$this->orchestratorMock->expects($this->once())->method('loadUserForEdit')->willReturn($this->user);
		$this->orchestratorMock->expects($this->once())->method('resetPassword')->with(12, $post)
			->willReturn(['success' => false, 'errors' => ['Token exists.']]);
		$this->orchestratorMock->expects($this->never())->method('storeEditSettings');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'Token exists.');

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRedirectedTo('/users/edit/12', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreResetPassword(): void
	{
		$this->orchestratorMock->expects($this->once())->method('loadUserForEdit')->willReturn($this->user);
		$this->orchestratorMock->expects($this->once())->method('resetPassword')->willReturn(['success' => true]);
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'User “heidi“ Password reset was successfully.');

		$response = $this->controller->store($this->createRequest(['UID' => '12', 'username' => 'heidi', 'resetPassword' => '']), new Response());

		$this->assertRedirectedTo('/users/edit/12', $response);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function expectCreateRendering(array $post): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildCreateForm')->with($post)->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepareCreateSettings')->with($this->form)
			->willReturn($this->templateData);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function expectEditRendering(array $post): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildEditForm')->with($post)->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepareEditSettings')->with($this->form, 'heidi')
			->willReturn($this->templateData);
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

	private function assertRendered(ResponseInterface $response): void
	{
		static::assertSame(200, $response->getStatusCode());
		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame($this->templateData['this_layout']['template'], $response->getTemplate());
		static::assertSame($this->templateData['this_layout']['data'], $response->getTemplateData());
		static::assertSame($this->templateData['main_layout'], $response->getMainLayoutData());
	}

	private function assertRedirectedTo(string $location, ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
	}
}
