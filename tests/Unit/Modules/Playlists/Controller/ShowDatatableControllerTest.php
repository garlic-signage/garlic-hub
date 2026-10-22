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

namespace Tests\Unit\Modules\Playlists\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Playlists\Controller\ShowDatatableController;
use App\Modules\Playlists\Helper\Datatable\DatatableFacade as Facade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ShowDatatableControllerTest extends TestCase
{
	private ShowDatatableController $controller;
	private Facade&MockObject $facadeMock;
	private DatatableTemplatePreparer&MockObject $templatePreparerMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private Translator&Stub $translatorStub;
	private Session&Stub $sessionStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->facadeMock           = $this->createMock(Facade::class);
		$this->templatePreparerMock = $this->createMock(DatatableTemplatePreparer::class);
		$this->requestMock          = $this->createMock(ServerRequestInterface::class);
		$this->responseMock         = $this->createMock(ResponseInterface::class);
		$this->translatorStub       = $this->createStub(Translator::class);
		$this->sessionStub          = $this->createStub(Session::class);

		$this->controller = new ShowDatatableController($this->facadeMock, $this->templatePreparerMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowMethodReturnsPageResponse(): void
	{
		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['translator', null, $this->translatorStub],
				['session', null, $this->sessionStub],
			]);

		$this->facadeMock->expects($this->once())->method('configure')
			->with($this->translatorStub, $this->sessionStub);

		$this->requestMock->method('getQueryParams')->willReturn(['page' => 2]);
		$this->facadeMock->expects($this->once())->method('processSubmittedUserInput')->with(['page' => 2]);
		$this->facadeMock->expects($this->once())->method('prepareDataGrid');
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->willReturn(['dataGrid' => 'value']);

		$templateData = ['main_layout' => [], 'this_layout' => ['template' => 'generic/datatable', 'data' => ['key' => 'value']]];
		$this->templatePreparerMock->expects($this->once())->method('preparerUITemplate')
			->with(['dataGrid' => 'value'])
			->willReturn($templateData);

		$templateData['this_layout']['data']['create_playlist_contextmenu'] = ['a contextmenu stub'];

		$this->facadeMock->expects($this->once())->method('prepareContextMenu')->willReturn( ['a contextmenu stub']);


		$this->responseMock->expects($this->never())->method($this->anything());

		$result = $this->controller->show($this->requestMock, $this->responseMock);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame($templateData['this_layout']['template'], $result->getTemplate());
		static::assertSame($templateData['this_layout']['data'], $result->getTemplateData());
	}

}
