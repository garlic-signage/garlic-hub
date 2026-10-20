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
use App\Modules\Templates\Controller\ShowComposerController;
use App\Modules\Templates\Helper\Composer\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowComposerControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private ResponseInterface&MockObject $responseMock;
	private Messages&MockObject $flashMock;
	private ServerRequestInterface $request;
	private ShowComposerController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock = $this->createMock(Orchestrator::class);
		$this->responseMock     = $this->createMock(ResponseInterface::class);
		$this->flashMock        = $this->createMock(Messages::class);
		$request                = $this->createStub(ServerRequestInterface::class);
		$request->method('getAttribute')->willReturnMap([['flash', null, $this->flashMock]]);
		$this->request = $request;

		$this->controller = new ShowComposerController($this->orchestratorMock);
	}

    /**
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testShowWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('build');
		$this->flashMock->expects($this->once())->method('addMessage')->with('error', 'No rights');
		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/templates')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$this->controller->show($this->request, $this->responseMock, ['template_id' => '12']);
	}

    /**
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testShow(): void
	{
		$composer = ['main_layout' => ['title' => 'x'], 'this_layout' => ['template' => 'templates/canvas', 'data' => []]];
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('build')->with(12)->willReturn($composer);
		$this->flashMock->expects($this->never())->method($this->anything());
		$streamMock = $this->createMock(StreamInterface::class);
		$streamMock->expects($this->once())->method('write')->with(serialize($composer));
		$this->responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);
		$this->responseMock->expects($this->once())->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(200)->willReturnSelf();

		$this->controller->show($this->request, $this->responseMock, ['template_id' => '12']);
	}
}
