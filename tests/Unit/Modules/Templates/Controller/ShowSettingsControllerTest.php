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

namespace Tests\Unit\Modules\Templates\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Controller\ShowSettingsController;
use App\Modules\Templates\Helper\Settings\Orchestrator;
use App\Modules\Templates\Helper\Settings\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowSettingsControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private ResponseInterface&MockObject $responseMock;
	private Messages&MockObject $flashMock;
	private ShowSettingsController $controller;

	/** @var array{hidden: list<array<string,string>>, visible: list<array<string,string>>} */
	private array $form = ['hidden' => [], 'visible' => [['name' => 'x']]];
	/** @var array{main_layout: array<string,mixed>, this_layout: array<string,mixed>} */
	private array $prepared = ['main_layout' => ['title' => 'x'], 'this_layout' => ['template' => 'templates/edit']];

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->templatePreparerMock = $this->createMock(TemplatePreparer::class);
		$this->responseMock         = $this->createMock(ResponseInterface::class);
		$this->flashMock            = $this->createMock(Messages::class);

		$this->controller = new ShowSettingsController($this->orchestratorMock, $this->templatePreparerMock);
	}

    /**
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testCreateWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkCreateRights')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('buildCreateForm');
		$this->expectNoRightsRedirect();

		$this->controller->create($this->request(), $this->responseMock, ['type' => 'canvas']);
	}

    /**
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testCreate(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkCreateRights')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('buildCreateForm')->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepareCreateSettings')->with($this->form)->willReturn($this->prepared);
		$this->flashMock->expects($this->never())->method($this->anything());
		$this->expectHtml();

		$this->controller->create($this->request(), $this->responseMock, ['type' => 'canvas']);
	}

    /**
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testEditWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('buildEditForm');
		$this->expectNoRightsRedirect();

		$this->controller->edit($this->request(), $this->responseMock, ['template_id' => '12']);
	}

    /**
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testEdit(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('buildEditForm')->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepareEditSettings')->with($this->form)->willReturn($this->prepared);
		$this->flashMock->expects($this->never())->method($this->anything());
		$this->expectHtml();

		$this->controller->edit($this->request(), $this->responseMock, ['template_id' => '12']);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testStoreInsertWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkCreateRights')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('storeCreateSettings');
		$this->expectNoRightsRedirect();

		$this->controller->store($this->request(['name' => 'Tpl']), $this->responseMock);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testStoreInsertWithErrorsShowsFormAgain(): void
	{
		$post = ['name' => '', 'type' => 'canvas'];
		$this->orchestratorMock->expects($this->once())->method('checkCreateRights')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('storeCreateSettings')->with($post)
			->willReturn(['success' => false, 'errors' => ['Name is missing.', 'CSRF token mismatch.']]);
		$errors = [];
		$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
			->willReturnCallback(function (string $key, string $message) use (&$errors): void { $errors[] = $key . ':' . $message; });
		$this->orchestratorMock->expects($this->once())->method('buildCreateForm')->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepareCreateSettings')->with($this->form)->willReturn($this->prepared);
		$this->expectHtml();

		$this->controller->store($this->request($post), $this->responseMock);

		static::assertSame(['error:Name is missing.', 'error:CSRF token mismatch.'], $errors);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testStoreInsert(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkCreateRights')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('storeCreateSettings')->willReturn(['success' => true]);
		$this->orchestratorMock->expects($this->never())->method('checkEditRights');
		$this->expectSuccessRedirect();

		$this->controller->store($this->request(['name' => 'Tpl']), $this->responseMock);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testStoreUpdateWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('storeEditSettings');
		$this->orchestratorMock->expects($this->never())->method('checkCreateRights');
		$this->expectNoRightsRedirect();

		$this->controller->store($this->request(['template_id' => '12']), $this->responseMock);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testStoreUpdateWithErrorsShowsFormAgain(): void
	{
		$post = ['template_id' => '12', 'name' => ''];
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('storeEditSettings')->with(12, $post)
			->willReturn(['success' => false, 'errors' => ['Name is missing.']]);
		$this->flashMock->expects($this->once())->method('addMessageNow')->with('error', 'Name is missing.');
		$this->orchestratorMock->expects($this->once())->method('buildEditForm')->willReturn($this->form);
		$this->templatePreparerMock->expects($this->once())->method('prepareEditSettings')->with($this->form)->willReturn($this->prepared);
		$this->expectHtml();

		$this->controller->store($this->request($post), $this->responseMock);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testStoreUpdate(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('storeEditSettings')->willReturn(['success' => true]);
		$this->expectSuccessRedirect();

		$this->controller->store($this->request(['template_id' => '12', 'name' => 'Tpl']), $this->responseMock);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function request(array $post = []): ServerRequestInterface
	{
		$request = $this->createStub(ServerRequestInterface::class);
		$request->method('getAttribute')->willReturnMap([['flash', null, $this->flashMock]]);
		$request->method('getParsedBody')->willReturn($post);

		return $request;
	}

	private function expectNoRightsRedirect(): void
	{
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'No rights');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->expectRedirect();
	}

	private function expectSuccessRedirect(): void
	{
		$this->flashMock->expects($this->once())->method('addMessage')->with('success', 'Templated saved successfully');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->expectRedirect();
	}

	private function expectRedirect(): void
	{
		$this->responseMock->expects($this->never())->method('getBody');
		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/templates')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();
	}

	private function expectHtml(): void
	{
		$streamMock = $this->createMock(StreamInterface::class);
		$streamMock->expects($this->once())->method('write')->with(serialize($this->prepared));
		$this->responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);
		$this->responseMock->expects($this->once())->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(200)->willReturnSelf();
	}
}
