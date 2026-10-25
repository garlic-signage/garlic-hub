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

namespace Tests\Unit\Framework\Utils\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Datatable\Paginator\Builder as PaginatorBuilder;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\Builder as ResultsBuilder;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Security\MemorySession;

/**
 * PrepareService with the real preparers for form, pager, header and body. Only the Translator is a stub.
 */
class PrepareServiceTest extends TestCase
{
	private FormBuilder $formBuilder;
	private BodyPreparer $bodyPreparer;
	private PrepareService $prepareService;

	protected function setUp(): void
	{
		parent::setUp();

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key . '@' . $module);

		$this->formBuilder   = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession()));
		$this->bodyPreparer  = new BodyPreparer();
		$this->prepareService = new PrepareService(
			$this->formBuilder,
			new Preparer(new UrlBuilder()),
			new HeaderPreparer(new TranslatorManager($translator), new UrlBuilder()),
			$this->bodyPreparer
		);
	}

	/**
	 * @param array<string,mixed> $input
	 * @throws ModuleException
	 */
	private function configure(array $input = []): void
	{
		$this->prepareService->configure(new FilterParametersFake($input), 'playlists');
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareFormSplitsHiddenAndVisibleFields(): void
	{
		$form = [
			'id'   => $this->formBuilder->createField(['id' => 'playlist_id', 'type' => FieldType::HIDDEN, 'name' => 'playlist_id', 'value' => 5]),
			'name' => $this->formBuilder->createField(['id' => 'name', 'type' => FieldType::TEXT, 'name' => 'name', 'label' => 'Name']),
		];

		$result = $this->prepareService->prepareForm($form);

		static::assertSame([['HIDDEN_HTML_ELEMENT' => '<input type="hidden" name="playlist_id" id="playlist_id" value="5">']], $result['hidden']);
		static::assertSame(['name'], array_column($result['visible'], 'HTML_ELEMENT_ID'));
	}

	/**
	 * The module name is the site of the links: they are relative ("playlists?...") and belong to the page /<module>,
	 * like the action of the filter form.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPreparePaginationHasLinksAndDropdownOfTheSite(): void
	{
		$this->configure(['elements_page' => '2', 'elements_per_page' => '20', 'sort_column' => 'created', 'sort_order' => 'DESC']);
		$pager = (new PaginatorBuilder())->configure(2, 20, 60, false, false)->buildPagerLinks()->getPagerLinks();

		$result = $this->prepareService->preparePagination($pager, ['min' => 10, 'max' => 30, 'steps' => 10]);

		static::assertSame(
			[
				'playlists?elements_page=1&sort_column=created&sort_order=DESC&elements_per_page=20',
				'playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=20',
				'playlists?elements_page=3&sort_column=created&sort_order=DESC&elements_per_page=20',
			],
			array_column($result['links'], 'ELEMENTS_PAGELINK')
		);
		static::assertSame(['', 'active_page', ''], array_column($result['links'], 'ELEMENTS_ACTIVE_PAGE'));
		static::assertSame([10, 20, 30], array_column($result['dropdown'], 'ELEMENTS_PER_PAGE_VALUE'));
		static::assertSame(['', 'selected', ''], array_column($result['dropdown'], 'ELEMENTS_PER_PAGE_SELECTED'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareDatatableHeaderUsesTheSiteAndTheLanguageModules(): void
	{
		$this->configure(['sort_column' => 'name', 'sort_order' => 'ASC']);
		$builder = new ResultsBuilder();
		$builder->createField('name', true);
		$builder->createField('type', false);

		$header = $this->prepareService->prepareDatatableHeader($builder->getHeaderFields(), ['playlists', 'main']);

		static::assertSame(
			[
				'SORTABLE_ORDER'          => '▼',
				'SORT_CONTROL_NAME'       => 'name',
				'LINK_CONTROL_SORT_ORDER' => 'playlists?elements_page=1&sort_column=name&sort_order=desc&elements_per_page=10',
				'LANG_CONTROL_NAME'       => 'name@playlists',
			],
			$header[0]['if_sortable']
		);
		static::assertSame('type@playlists', $header[1]['LANG_CONTROL_NAME_2']);
	}

	#[Group('units')]
	public function testGetBodyPreparer(): void
	{
		static::assertSame($this->bodyPreparer, $this->prepareService->getBodyPreparer());
	}

	/**
	 * The service has to be configured with the parameters before the pager or the header can be prepared.
	 */
	#[Group('units')]
	public function testTheHeaderCanNotBePreparedBeforeConfigure(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('PrepareService::configure() has to be called before the header is prepared.');

		$this->prepareService->prepareDatatableHeader([], ['main']);
	}

	#[Group('units')]
	public function testThePaginationCanNotBePreparedBeforeConfigure(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Preparer::setBaseFilter() has to be called');

		$this->prepareService->preparePagination([['name' => '1', 'page' => 1]], ['min' => 10, 'max' => 20, 'steps' => 10]);
	}
}
