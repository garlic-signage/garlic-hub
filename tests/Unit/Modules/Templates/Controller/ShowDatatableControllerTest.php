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

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Templates\Controller\ShowDatatableController;
use App\Modules\Templates\Helper\Datatable\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ShowDatatableControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private DatatableTemplatePreparer&MockObject $templatePreparerMock;
	private ResponseInterface&MockObject $responseMock;
	private ServerRequestInterface $request;
	private ShowDatatableController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->templatePreparerMock = $this->createMock(DatatableTemplatePreparer::class);
		$this->responseMock         = $this->createMock(ResponseInterface::class);
		$request                    = $this->createStub(ServerRequestInterface::class);
		$request->method('getQueryParams')->willReturn(['elements_page' => 2]);
		$this->request = $request;

		$this->controller = new ShowDatatableController($this->orchestratorMock, $this->templatePreparerMock);
	}

    /**
     * @throws \DateMalformedStringException
     * @throws ModuleException
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testShowWithoutRightsRedirectsToHome(): void
	{
		$this->orchestratorMock->expects($this->once())->method('configure');
		$this->orchestratorMock->expects($this->once())->method('checkRights')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('processSubmittedUserInput');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		static::assertSame($this->responseMock, $this->controller->show($this->request, $this->responseMock));
	}

    /**
     * @throws \DateMalformedStringException
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testShow(): void
	{
		$this->orchestratorMock->expects($this->once())->method('configure');
		$this->orchestratorMock->expects($this->once())->method('checkRights')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('processSubmittedUserInput')->with(['elements_page' => 2]);
		$this->orchestratorMock->expects($this->once())->method('prepareDataGrid')->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('prepareUITemplate')->willReturn(['grid' => 'data']);
		$this->orchestratorMock->expects($this->once())->method('prepareContextMenu')->willReturn(['LANG_COMPOSE' => 'Composer']);
		$this->templatePreparerMock->expects($this->once())->method('preparerUITemplate')->with(['grid' => 'data'])
			->willReturn(['main_layout' => ['title' => 't'], 'this_layout' => ['template' => 'x', 'data' => ['a' => 'b']]]);

		$expected = ['main_layout' => ['title' => 't'], 'this_layout' => ['template' => 'x', 'data' => [
			'a' => 'b',
			'templates_actions_contextmenu' => ['LANG_COMPOSE' => 'Composer']
		]]];
		$this->responseMock->expects($this->never())->method($this->anything());

		$result = $this->controller->show($this->request, $this->responseMock);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame($expected['this_layout']['template'], $result->getTemplate());
		static::assertSame($expected['this_layout']['data'], $result->getTemplateData());
		static::assertSame($expected['main_layout'], $result->getMainLayoutData());
	}
}
