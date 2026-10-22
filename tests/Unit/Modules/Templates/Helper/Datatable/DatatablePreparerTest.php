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

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Templates\Helper\Datatable\DatatablePreparer;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\AclValidator;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatatablePreparerTest extends TestCase
{
	private BodyPreparer&MockObject $bodyPreparerMock;
	private AclValidator&MockObject $aclValidatorMock;
	private DatatablePreparer $preparer;

	/** @var array{UID: int, company_id: int, template_id: int, type: string, name: string, username: string, visibility: string} */
	private array $template = [
		'UID' => 5, 'company_id' => 1, 'template_id' => 12, 'type' => 'canvas',
		'name' => 'Tpl', 'username' => 'admin', 'visibility' => 'public'
	];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$prepareServiceStub     = $this->createStub(PrepareService::class);
		$prepareServiceStub->method('getBodyPreparer')->willReturn($this->bodyPreparerMock);

		$translatorStub = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key);
		$translatorStub->method('translateArrayForOptions')->willReturn(['canvas' => 'Canvas']);

		$this->preparer = new DatatablePreparer($prepareServiceStub, $this->aclValidatorMock, $this->createStub(Parameters::class), $translatorStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareTableBodyForOwner(): void
	{
		$this->preparer->setUsedTemplates([12 => 3]);
		$this->aclValidatorMock->expects($this->never())->method('isTemplateEditable');

		$this->bodyPreparerMock->expects($this->once())->method('formatUID')->with(5, 'admin')->willReturn(['uid']);
		$this->bodyPreparerMock->expects($this->once())->method('formatLink')
			->with('Tpl', 'templates.composer: Tpl', '/templates/composer/12', '12')
			->willReturn(['link']);
		$this->bodyPreparerMock->expects($this->exactly(3))->method('formatText')
			->willReturnCallback(fn(string $text): array => ['text' => $text]);
		$this->bodyPreparerMock->expects($this->once())->method('formatAction')
			->with('templates.actions', 'template-contextmenu', '12', 'three-dots template-contextmenu')
			->willReturn(['action']);

		$result = $this->preparer->prepareTableBody([$this->template], $this->fields(['name', 'type', 'UID', 'used', 'visibility']), 5);

		static::assertSame([[
			'UNIT_ID' => 12,
			'elements_result_element' => [
				['CONTROL_NAME_BODY' => 'name', 'is_link' => ['link']],
				['CONTROL_NAME_BODY' => 'type', 'is_text' => ['text' => 'Canvas']],
				['CONTROL_NAME_BODY' => 'UID', 'is_UID' => ['uid']],
				['CONTROL_NAME_BODY' => 'used', 'is_text' => ['text' => '3']],
				['CONTROL_NAME_BODY' => 'visibility', 'is_text' => ['text' => 'public']],
			],
			'has_action' => [['action']]
		]], $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareTableBodyUnusedTemplateWithoutRights(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isTemplateEditable')
			->with(9, $this->template)
			->willReturn(false);
		$this->bodyPreparerMock->expects($this->once())->method('formatText')->with('0')->willReturn(['0']);
		$this->bodyPreparerMock->expects($this->never())->method('formatAction');

		$result = $this->preparer->prepareTableBody([$this->template], $this->fields(['used']), 9);

		static::assertArrayNotHasKey('has_action', $result[0]);
		static::assertSame([['CONTROL_NAME_BODY' => 'used', 'is_text' => ['0']]], $result[0]['elements_result_element']);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareTableBodyForEditor(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isTemplateEditable')->with(9, $this->template)->willReturn(true);
		$this->bodyPreparerMock->expects($this->once())->method('formatAction')->willReturn(['action']);

		$result = $this->preparer->prepareTableBody([$this->template], [], 9);

		static::assertSame([['action']], $result[0]['has_action']);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithoutResults(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->bodyPreparerMock->expects($this->never())->method($this->anything());

		static::assertSame([], $this->preparer->prepareTableBody([], $this->fields(['name']), 5));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFormatContextMenu(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->bodyPreparerMock->expects($this->never())->method($this->anything());

		static::assertSame([
			'LANG_COMPOSE' => 'templates.composer',
			'LANG_SETTINGS' => 'main.settings',
			'LANG_TEMPLATE_DELETE' => 'main.delete',
			'LANG_TEMPLATE_DELETE_CONFIRM' => 'templates.confirm_delete'
		], $this->preparer->formatContextMenu());
	}

	/**
	 * @param list<string> $names
	 * @return list<HeaderField>
	 */
	private function fields(array $names): array
	{
		$fields = [];
		foreach ($names as $name)
		{
			$field = $this->createStub(HeaderField::class);
			$field->method('getName')->willReturn($name);
			$fields[] = $field;
		}

		return $fields;
	}
}
