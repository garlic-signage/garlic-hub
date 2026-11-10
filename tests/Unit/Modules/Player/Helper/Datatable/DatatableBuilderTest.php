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

namespace Tests\Unit\Modules\Player\Helper\Datatable;

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
use App\Modules\Player\Helper\Datatable\DatatableBuilder;
use App\Modules\Player\Helper\Datatable\Parameters;
use App\Modules\Player\Services\AclValidator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The form and the columns of the player list with the real parameters and the real services of the datatable.
 * The translator gives module.key back, the options of a dropdown only for its own key.
 */
class DatatableBuilderTest extends TestCase
{
	private Parameters $parameters;
	private BuildService $buildService;
	private DatatableBuilder $builder;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturnCallback(static fn(string $key, string $module): array => match ([$key, $module])
		{
			['activity_selects', 'player'] => ['active' => 'Active'],
			['model_selects', 'player']    => ['3' => 'Model 3'],
			default                        => []
		});
		$session = new MemorySession();

		$this->parameters   = new Parameters(new Sanitizer(), $session);
		$this->buildService = new BuildService(
			new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session)),
			new PaginatorBuilder(),
			new ResultsBuilder()
		);
		$this->builder = new DatatableBuilder($this->buildService, $this->parameters, $this->createStub(AclValidator::class), $translator);
	}

	/**
	 * The parameters of an admin: activity and owner, with values of a request.
	 */
	private function adminParameters(): void
	{
		$this->parameters->addActivity();
		$this->parameters->addOwner();
		$this->parameters->setUserInputs([]);
		$this->parameters->parseInputFilterAllUsers(['player_name']);
		$this->parameters->setValueOfParameter('activity', 'active');
		$this->parameters->setValueOfParameter('player_name', 'Lobby');
		$this->parameters->setValueOfParameter('model', '3');
		$this->parameters->setValueOfParameter('UID', 4);
	}

	#[Group('units')]
	public function testTheTitle(): void
	{
		$this->builder->buildTitle();

		static::assertSame('player.overview', $this->builder->getDatatableStructure()['title']);
	}

	#[Group('units')]
	public function testTheFormOfAnAdminHasActivityNameModelAndOwner(): void
	{
		$this->adminParameters();

		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];
		static::assertSame(['activity', 'player_name', 'model', 'UID'], array_keys($form));
		static::assertSame([
			[FieldType::DROPDOWN, 'player.activity', 'player.activity', 'active'],
			[FieldType::TEXT, 'player.player_name', 'player.player_name', 'Lobby'],
			[FieldType::DROPDOWN, 'player.model', 'player.model', '3'],
			[FieldType::AUTOCOMPLETE, 'main.owner', 'main.owner', '4']
		], array_map(static fn($field): array => [$field->getType(), $field->getTitle(), $field->getLabel(), $field->getValue()], array_values($form)));
		static::assertInstanceOf(DropdownField::class, $form['activity']);
		static::assertSame(['active' => 'Active'], $form['activity']->getOptions());
		static::assertInstanceOf(DropdownField::class, $form['model']);
		static::assertSame(['3' => 'Model 3'], $form['model']->getOptions());
	}

	#[Group('units')]
	public function testTheFormOfAUserHasNameAndModel(): void
	{
		$this->parameters->setUserInputs([]);
		$this->parameters->parseInputFilterAllUsers(['player_name']);

		$this->builder->collectFormElements();

		static::assertSame(['player_name', 'model'], array_keys($this->builder->getDatatableStructure()['form']));
	}

	#[Group('units')]
	public function testTheTableOfAnAdminHasTheOwner(): void
	{
		$this->parameters->addOwner();

		$this->builder->createTableFields();

		$fields = $this->buildService->getDatatableFields();
		static::assertSame(['last_access', 'player_name', 'UID', 'model', 'playlist_id'], array_map(static fn($field): string => $field->getName(), $fields));
		static::assertSame([true, true, true, true, false], array_map(static fn($field): bool => $field->isSortable(), $fields));
		static::assertCount(5, $this->builder->getDatatableStructure()['header']);
	}

	#[Group('units')]
	public function testTheTableOfAUserHasNoOwner(): void
	{
		$this->builder->createTableFields();

		static::assertSame(['last_access', 'player_name', 'model', 'playlist_id'], array_map(static fn($field): string => $field->getName(), $this->buildService->getDatatableFields()));
	}
}
