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

namespace Tests\Unit\Modules\Playlists\Helper\Datatable;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\Paginator\Builder as PaginatorBuilder;
use App\Framework\Utils\Datatable\Results\Builder as ResultsBuilder;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Validation\Sanitizer;
use App\Modules\Playlists\Helper\Datatable\DatatableBuilder;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Services\AclValidator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The builder of the playlist list with the real parameters and the real services of the datatable.
 * The container of the integration tests knows only the edge edition: the editions are tested here.
 */
class DatatableBuilderTest extends TestCase
{
	private Parameters $parameters;
	private AclValidator&Stub $aclValidatorStub;
	private DatatableBuilder $builder;
	private BuildService $buildService;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn(['master' => 'Master', 'internal' => 'Internal', 'external' => 'External', 'channel' => 'Channel']);
		$session = new MemorySession();

		$this->parameters       = new Parameters(new Sanitizer(), $session);
		$this->aclValidatorStub = $this->createStub(AclValidator::class);
		$this->buildService     = new BuildService(
			new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session)),
			new PaginatorBuilder(),
			new ResultsBuilder()
		);
		$this->builder = new DatatableBuilder($this->buildService, $this->parameters, $this->aclValidatorStub, $translator);
	}

	/**
	 * The form shows the values of the request, which are parsed before.
	 */
	private function parseRequest(): void
	{
		$this->parameters->setUserInputs([]);
		$this->parameters->parseInputFilterAllUsers(['playlist_name']);
	}

	private function edition(string $edition): void
	{
		$config = $this->createStub(Config::class);
		$config->method('getEdition')->willReturn($edition);
		$this->aclValidatorStub->method('getConfig')->willReturn($config);
	}

	#[Group('units')]
	public function testTheEdgeEditionAddsNoParameters(): void
	{
		$this->edition(Config::PLATFORM_EDITION_EDGE);
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(true);

		$this->builder->configureParameters(123);

		static::assertFalse($this->parameters->hasParameter('UID'));
		static::assertFalse($this->parameters->hasParameter('company_id'));
	}

	#[Group('units')]
	public function testTheModuleAdminFiltersByOwnerAndCompany(): void
	{
		$this->edition('some_other_edition');
		$this->aclValidatorStub->method('isModuleAdmin')->willReturnMap([[123, true]]);
		$this->aclValidatorStub->method('isSubAdmin')->willReturn(false);

		$this->builder->configureParameters(123);

		static::assertTrue($this->parameters->hasParameter('UID'));
		static::assertTrue($this->parameters->hasParameter('company_id'));
	}

	#[Group('units')]
	public function testTheSubAdminFiltersByOwnerAndCompany(): void
	{
		$this->edition('some_other_edition');
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorStub->method('isSubAdmin')->willReturnMap([[456, true]]);

		$this->builder->configureParameters(456);

		static::assertTrue($this->parameters->hasParameter('UID'));
		static::assertTrue($this->parameters->hasParameter('company_id'));
	}

	#[Group('units')]
	public function testAUserWithoutRightsFiltersOnlyByTheModuleParameters(): void
	{
		$this->edition('some_other_edition');
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorStub->method('isSubAdmin')->willReturn(false);

		$this->builder->configureParameters(789);

		static::assertFalse($this->parameters->hasParameter('UID'));
		static::assertTrue($this->parameters->hasParameter('playlist_name'));
	}

	#[Group('units')]
	public function testTheQueryIsParsedAndOnlySortableColumnsCanBeSorted(): void
	{
		$this->builder->determineParameters(['playlist_name' => 'Lobby', 'sort_column' => 'duration', 'elements_per_page' => '20']);

		static::assertSame('Lobby', $this->parameters->getValueOfParameter('playlist_name'));
		static::assertSame(['playlist_name', 'playlist_mode'], array_map(static fn($field): string => $field->getName(), array_filter($this->buildService->getDatatableFields(), static fn($field): bool => $field->isSortable())));
		// duration is not sortable: the default order stays
		static::assertSame('playlist_id', $this->parameters->getValueOfParameter('sort_column'));
		static::assertSame(20, $this->parameters->getValueOfParameter('elements_per_page'));
	}

	#[Group('units')]
	public function testTheTitle(): void
	{
		$this->builder->buildTitle();

		static::assertSame('playlists.overview', $this->builder->getDatatableStructure()['title']);
	}

	#[Group('units')]
	public function testTheFormHasTheNameTheModeAndForAnAdminTheOwner(): void
	{
		$this->edition(Config::PLATFORM_EDITION_CORE);
		$this->parameters->addOwner();
		$this->parseRequest();

		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];
		static::assertSame(['playlist_name', 'UID', 'playlist_mode'], array_keys($form));
		static::assertSame([FieldType::TEXT, FieldType::AUTOCOMPLETE, FieldType::DROPDOWN], array_map(static fn($field): FieldType => $field->getType(), array_values($form)));
		static::assertSame('playlists.playlist_name', $form['playlist_name']->getLabel());
		static::assertSame('main.owner', $form['UID']->getLabel());
	}

	#[Group('units')]
	public function testTheFormOfAUserHasNoOwner(): void
	{
		$this->edition(Config::PLATFORM_EDITION_EDGE);
		$this->parseRequest();

		$this->builder->collectFormElements();

		static::assertSame(['playlist_name', 'playlist_mode'], array_keys($this->builder->getDatatableStructure()['form']));
	}

	#[Group('units')]
	public function testTheEdgeEditionKnowsNoExternalAndNoChannelPlaylists(): void
	{
		$this->edition(Config::PLATFORM_EDITION_EDGE);

		static::assertSame(['master' => 'Master', 'internal' => 'Internal'], $this->builder->determineAllowedPlaylistModes());

		$this->parseRequest();
		$this->builder->collectFormElements();
		$mode = $this->builder->getDatatableStructure()['form']['playlist_mode'];
		static::assertInstanceOf(DropdownField::class, $mode);
		static::assertSame(['master' => 'Master', 'internal' => 'Internal'], $mode->getOptions());
	}

	#[Group('units')]
	public function testTheOtherEditionsKnowAllPlaylistModes(): void
	{
		$this->edition(Config::PLATFORM_EDITION_CORE);

		static::assertSame(['master', 'internal', 'external', 'channel'], array_keys($this->builder->determineAllowedPlaylistModes()));
	}

	#[Group('units')]
	public function testTheTableHasTheNameTheModeAndTheDurationAndForAnAdminTheOwner(): void
	{
		$this->builder->createTableFields();

		static::assertSame(['playlist_name', 'playlist_mode', 'duration'], array_map(static fn($field): string => $field->getName(), $this->buildService->getDatatableFields()));
	}

	#[Group('units')]
	public function testTheTableOfAnAdminHasTheOwner(): void
	{
		$this->parameters->addOwner();

		$this->builder->createTableFields();

		static::assertSame(['playlist_name', 'UID', 'playlist_mode', 'duration'], array_map(static fn($field): string => $field->getName(), $this->buildService->getDatatableFields()));
		static::assertSame([true, true, true, false], array_map(static fn($field): bool => $field->isSortable(), $this->buildService->getDatatableFields()));
		static::assertCount(4, $this->builder->getDatatableStructure()['header']);
	}
}
