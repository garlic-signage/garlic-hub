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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Controller\ShowInitialAdminController;
use App\Modules\Users\Helper\InitialAdmin\Orchestrator;
use App\Modules\Users\Helper\InitialAdmin\TemplatePreparer;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

class ShowInitialAdminControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private Messages&MockObject $flashMock;
	private ShowInitialAdminController $controller;

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

		$this->controller = new ShowInitialAdminController($this->orchestratorMock, $this->templatePreparerMock, $this->flashMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowNotAllowedRedirects(): void
	{
		$this->orchestratorMock->expects($this->once())->method('isFunctionAllowed')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('buildForm');
		$this->templatePreparerMock->expects($this->never())->method('prepare');
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response());

		$this->assertRedirectedTo('/', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowRendersForm(): void
	{
		$this->orchestratorMock->expects($this->once())->method('isFunctionAllowed')->willReturn(true);
		$this->expectRendering([]);
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->show($this->createStub(ServerRequestInterface::class), new Response());

		$this->assertRendered($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreNotAllowedRedirects(): void
	{
		$this->orchestratorMock->expects($this->once())->method('isFunctionAllowed')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('store');
		$this->templatePreparerMock->expects($this->never())->method('prepare');
		$this->flashMock->expects($this->never())->method($this->anything());

		$response = $this->controller->store($this->createRequest(['username' => 'admin']), new Response());

		$this->assertRedirectedTo('/', $response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreWithErrorsShowsFormAgain(): void
	{
		$post = ['username' => 'admin', 'email' => ''];
		$this->orchestratorMock->expects($this->once())->method('isFunctionAllowed')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('store')->with($post)
			->willReturn(['success' => false, 'errors' => ['No email.', 'No password.']]);
		$errors = [];
		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnCallback(function (string $key, string $message) use (&$errors): void { $errors[] = $key . ':' . $message; });
		$this->flashMock->expects($this->never())->method('addMessage');
		$this->expectRendering($post);

		$response = $this->controller->store($this->createRequest($post), new Response());

		$this->assertRendered($response);
		static::assertSame(['error:No email.', 'error:No password.'], $errors);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreRedirectsToLogin(): void
	{
		$this->orchestratorMock->expects($this->once())->method('isFunctionAllowed')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('store')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('buildForm');
		$this->templatePreparerMock->expects($this->never())->method('prepare');
		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'Admin User “admin“ successfully stored. You can now login with your username and password.');

		$response = $this->controller->store($this->createRequest(['username' => 'admin']), new Response());

		$this->assertRedirectedTo('/login', $response);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function expectRendering(array $post): void
	{
		$this->orchestratorMock->expects($this->once())->method('buildForm')->with($post)->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepare')->with($this->form)
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
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame(serialize($this->templateData), (string) $response->getBody());
	}

	private function assertRedirectedTo(string $location, ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
	}
}
