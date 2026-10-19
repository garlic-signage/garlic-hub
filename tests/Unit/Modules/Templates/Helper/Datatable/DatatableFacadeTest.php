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

namespace Tests\Unit\Modules\Templates\Helper\Datatable;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Templates\Helper\Datatable\DatatableBuilder;
use App\Modules\Templates\Helper\Datatable\DatatableFacade;
use App\Modules\Templates\Helper\Datatable\DatatablePreparer;
use App\Modules\Templates\Services\TemplatesDatatableService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class DatatableFacadeTest extends TestCase
{
	private DatatableBuilder&MockObject $builderMock;
	private DatatablePreparer&MockObject $preparerMock;
	private TemplatesDatatableService&MockObject $serviceMock;
	private Translator&Stub $translatorStub;
	private Session&Stub $sessionStub;
	private DatatableFacade $facade;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->builderMock    = $this->createMock(DatatableBuilder::class);
		$this->preparerMock   = $this->createMock(DatatablePreparer::class);
		$this->serviceMock    = $this->createMock(TemplatesDatatableService::class);
		$this->translatorStub = $this->createStub(Translator::class);
		$this->sessionStub    = $this->createStub(Session::class);
		$this->sessionStub->method('get')->willReturnMap([['user', ['UID' => '7']]]);

		$this->facade = new DatatableFacade($this->builderMock, $this->preparerMock, $this->serviceMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testConfigure(): void
	{
		$this->builderMock->expects($this->once())->method('configureParameters')->with(7);
		$this->builderMock->expects($this->once())->method('setTranslator')->with($this->translatorStub);
		$this->preparerMock->expects($this->once())->method('setTranslator')->with($this->translatorStub);
		$this->serviceMock->expects($this->never())->method($this->anything());

		$this->facade->configure($this->translatorStub, $this->sessionStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckRights(): void
	{
		$this->serviceMock->expects($this->once())->method('checkDisplayRights')->willReturn(true);
		$this->builderMock->expects($this->never())->method($this->anything());
		$this->preparerMock->expects($this->never())->method($this->anything());

		static::assertTrue($this->facade->checkRights());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testProcessSubmittedUserInput(): void
	{
		$this->builderMock->expects($this->once())->method('determineParameters')->with(['page' => 2]);
		$this->serviceMock->expects($this->once())->method('loadDatatable');
		$this->preparerMock->expects($this->never())->method($this->anything());

		$this->facade->processSubmittedUserInput(['page' => 2]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testPrepareDataGrid(): void
	{
		$this->serviceMock->expects($this->once())->method('getCurrentTotalResult')->willReturn(3);
		$this->builderMock->expects($this->once())->method('buildTitle');
		$this->builderMock->expects($this->once())->method('collectFormElements');
		$this->builderMock->expects($this->once())->method('createPagination')->with(3);
		$this->builderMock->expects($this->once())->method('createDropDown');
		$this->builderMock->expects($this->once())->method('createTableFields');
		$this->preparerMock->expects($this->never())->method($this->anything());

		static::assertSame($this->facade, $this->facade->prepareDataGrid());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testPrepareUITemplate(): void
	{
		$this->facade->configure($this->translatorStub, $this->sessionStub);

		$header    = [$this->createStub(HeaderField::class)];
		$structure = [
			'pager' => ['links'],
			'dropdown' => ['options'],
			'form' => ['name' => 'field'],
			'header' => $header,
			'title' => 'Overview',
		];
		$rows = [['template_id' => 1], ['template_id' => 2]];

		$this->builderMock->expects($this->once())->method('getDatatableStructure')->willReturn($structure);
		$this->preparerMock->expects($this->once())->method('preparePagination')
			->with(['links'], ['options'])
			->willReturn(['dropdown' => ['10'], 'links' => ['next']]);
		$this->preparerMock->expects($this->once())->method('prepareFilterForm')->with(['name' => 'field'])->willReturn(['filter']);
		$this->preparerMock->expects($this->once())->method('prepareAdd')->with('file-plus', 'templates/settings/canvas')->willReturn(['add']);
		$this->preparerMock->expects($this->once())->method('prepareTableHeader')->with($header, ['templates', 'main'])->willReturn(['header']);
		$this->preparerMock->expects($this->once())->method('prepareSort')->willReturn(['sort']);
		$this->preparerMock->expects($this->once())->method('preparePage')->willReturn(['page']);
		$this->serviceMock->expects($this->once())->method('getCurrentFilterResults')->willReturn($rows);
		$this->serviceMock->expects($this->once())->method('getCurrentTotalResult')->willReturn(2);
		$this->serviceMock->expects($this->once())->method('getTemplatesInUse')->with([1, 2])->willReturn([2 => 1]);
		$this->preparerMock->expects($this->once())->method('setUsedTemplates')->with([2 => 1]);
		$this->preparerMock->expects($this->once())->method('prepareTableBody')->with($rows, $header, 7)->willReturn(['rows']);

		static::assertSame([
			'filter_elements'     => ['filter'],
			'pagination_dropdown' => ['10'],
			'pagination_links'    => ['next'],
			'has_add'             => ['add'],
			'results_header'      => ['header'],
			'results_list'        => ['rows'],
			'results_count'       => 2,
			'title'               => 'Overview',
			'template_name'       => 'templates/datatable',
			'module_name'         => 'templates',
			'additional_css'      => ['/css/templates/datatable.css'],
			'footer_modules'      => ['/js/templates/datatable/init.js'],
			'sort'                => ['sort'],
			'page'                => ['page']
		], $this->facade->prepareUITemplate());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testPrepareContextMenu(): void
	{
		$this->preparerMock->expects($this->once())->method('formatContextMenu')->willReturn(['LANG_COMPOSE' => 'Composer']);
		$this->builderMock->expects($this->never())->method($this->anything());
		$this->serviceMock->expects($this->never())->method($this->anything());

		static::assertSame(['LANG_COMPOSE' => 'Composer'], $this->facade->prepareContextMenu());
	}
}
