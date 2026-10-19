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

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Templates\Controller\ShowDatatableController;
use App\Modules\Templates\Helper\Datatable\DatatableFacade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class ShowDatatableControllerTest extends TestCase
{
	private DatatableFacade&MockObject $facadeMock;
	private DatatableTemplatePreparer&MockObject $templatePreparerMock;
	private ResponseInterface&MockObject $responseMock;
	private ServerRequestInterface $request;
	private Translator $translator;
	private Session $session;
	private ShowDatatableController $controller;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->facadeMock           = $this->createMock(DatatableFacade::class);
		$this->templatePreparerMock = $this->createMock(DatatableTemplatePreparer::class);
		$this->responseMock         = $this->createMock(ResponseInterface::class);
		$this->translator           = $this->createStub(Translator::class);
		$this->session              = $this->createStub(Session::class);
		$request                    = $this->createStub(ServerRequestInterface::class);
		$request->method('getAttribute')->willReturnMap([
			['translator', null, $this->translator],
			['session', null, $this->session],
		]);
		$request->method('getQueryParams')->willReturn(['elements_page' => 2]);
		$this->request = $request;

		$this->controller = new ShowDatatableController($this->facadeMock, $this->templatePreparerMock);
	}

	#[Group('units')]
	public function testShowWithoutRightsRedirectsToHome(): void
	{
		$this->facadeMock->expects($this->once())->method('configure')->with($this->translator, $this->session);
		$this->facadeMock->expects($this->once())->method('checkRights')->willReturn(false);
		$this->facadeMock->expects($this->never())->method('processSubmittedUserInput');
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		static::assertSame($this->responseMock, $this->controller->show($this->request, $this->responseMock));
	}

	#[Group('units')]
	public function testShow(): void
	{
		$this->facadeMock->expects($this->once())->method('configure');
		$this->facadeMock->expects($this->once())->method('checkRights')->willReturn(true);
		$this->facadeMock->expects($this->once())->method('processSubmittedUserInput')->with(['elements_page' => 2]);
		$this->facadeMock->expects($this->once())->method('prepareDataGrid')->willReturnSelf();
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')->willReturn(['grid' => 'data']);
		$this->facadeMock->expects($this->once())->method('prepareContextMenu')->willReturn(['LANG_COMPOSE' => 'Composer']);
		$this->templatePreparerMock->expects($this->once())->method('preparerUITemplate')->with(['grid' => 'data'])
			->willReturn(['main_layout' => ['title' => 't'], 'this_layout' => ['template' => 'x', 'data' => ['a' => 'b']]]);

		$expected = ['main_layout' => ['title' => 't'], 'this_layout' => ['template' => 'x', 'data' => [
			'a' => 'b',
			'templates_actions_contextmenu' => ['LANG_COMPOSE' => 'Composer']
		]]];
		$streamMock = $this->createMock(StreamInterface::class);
		$streamMock->expects($this->once())->method('write')->with(serialize($expected));
		$this->responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);
		$this->responseMock->expects($this->once())->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(200)->willReturnSelf();

		static::assertSame($this->responseMock, $this->controller->show($this->request, $this->responseMock));
	}
}
