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

namespace Tests\Unit\Modules\Templates\Helper\Datatable;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Validation\Sanitizer;
use App\Modules\Templates\Helper\Datatable\DatatablePreparer;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\AclValidator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The rows of the template list with the real services of the datatable.
 */
class DatatablePreparerTest extends TestCase
{
	private AclValidator&Stub $aclValidatorStub;
	private DatatablePreparer $preparer;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn(['canvas' => 'Canvas']);
		$session   = new MemorySession();
		$urlBuilder = new UrlBuilder();

		$this->aclValidatorStub = $this->createStub(AclValidator::class);
		$this->preparer = new DatatablePreparer(
			new PrepareService(
				new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session)),
				new Preparer($urlBuilder),
				new HeaderPreparer(new TranslatorManager($translator), $urlBuilder),
				new BodyPreparer()
			),
			$this->aclValidatorStub,
			new Parameters(new Sanitizer(), $session),
			$translator
		);
	}

	/**
	 * @param list<string> $names
	 * @return list<HeaderField>
	 */
	private function fields(array $names): array
	{
		return array_map(static fn(string $name): HeaderField => new HeaderField()->setName($name), $names);
	}

	/**
	 * @return array{UID: int, company_id: int, template_id: int, type: string, name: string, username: string}
	 */
	private static function template(int $id = 5, int $owner = 2, string $type = 'canvas'): array
	{
		return ['UID' => $owner, 'company_id' => 1, 'template_id' => $id, 'type' => $type, 'name' => 'Lobby', 'username' => 'anna'];
	}

	#[Group('units')]
	public function testWithoutTemplatesTheBodyIsEmpty(): void
	{
		static::assertSame([], $this->preparer->prepareTableBody([], $this->fields(['name']), 2));
	}

	#[Group('units')]
	public function testTheCellsOfARow(): void
	{
		$this->aclValidatorStub->method('isTemplateEditable')->willReturn(false);

		$body = $this->preparer->setUsedTemplates([5 => 3])->prepareTableBody([self::template()], $this->fields(['name', 'type', 'UID', 'used']), 3);

		static::assertSame(5, $body[0]['UNIT_ID']);
		static::assertSame(
			[
				['CONTROL_NAME_BODY' => 'name', 'is_link' => ['CONTROL_ELEMENT_VALUE_NAME' => 'Lobby', 'CONTROL_ELEMENT_VALUE_TITLE' => 'templates.composer: Lobby', 'CONTROL_ELEMENT_VALUE_LINK' => '/templates/composer/5', 'CONTROL_ELEMENT_VALUE_ID' => '5', 'CONTROL_ELEMENT_VALUE_CLASS' => '', 'CONTROL_ELEMENT_ADDITIONAL_TEXT' => '']],
				['CONTROL_NAME_BODY' => 'type', 'is_text' => ['CONTROL_ELEMENT_VALUE_TEXT' => 'Canvas']],
				['CONTROL_NAME_BODY' => 'UID', 'is_UID' => ['OWNER_UID' => 2, 'OWNER_NAME' => 'anna']],
				['CONTROL_NAME_BODY' => 'used', 'is_text' => ['CONTROL_ELEMENT_VALUE_TEXT' => '3']],
			],
			$body[0]['elements_result_element']
		);
	}

	#[Group('units')]
	public function testATemplateWhichIsNotUsedIsUsedZeroTimes(): void
	{
		$this->aclValidatorStub->method('isTemplateEditable')->willReturn(false);

		$body = $this->preparer->setUsedTemplates([6 => 2])->prepareTableBody([self::template()], $this->fields(['used']), 3);

		static::assertSame('0', $body[0]['elements_result_element'][0]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']);
	}

	#[Group('units')]
	public function testAnUnknownColumnIsShownAsText(): void
	{
		$this->aclValidatorStub->method('isTemplateEditable')->willReturn(false);

		$body = $this->preparer->prepareTableBody([self::template()], $this->fields(['company_id']), 3);

		static::assertSame('1', $body[0]['elements_result_element'][0]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']);
	}

	#[Group('units')]
	public function testATypeWithoutANameIsShownAsItIs(): void
	{
		$this->aclValidatorStub->method('isTemplateEditable')->willReturn(false);

		$body = $this->preparer->prepareTableBody([self::template(type: 'fabric.js')], $this->fields(['type']), 3);

		static::assertSame('fabric.js', $body[0]['elements_result_element'][0]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']);
	}

	#[Group('units')]
	public function testTheOwnerAndWhoMayEditGetTheContextMenu(): void
	{
		$this->aclValidatorStub->method('isTemplateEditable')->willReturn(false);
		$row = $this->preparer->prepareTableBody([self::template()], $this->fields(['name']), 2)[0];

		static::assertSame([['LANG_ACTION' => 'templates.actions', 'ACTION_ID' => '5', 'ACTION_NAME' => 'template-contextmenu', 'ACTION_ICON_CLASS' => 'three-dots template-contextmenu']], $row['has_action']);
	}

	#[Group('units')]
	public function testWhoMayEditATemplateOfAnotherUserGetsTheContextMenuToo(): void
	{
		$this->aclValidatorStub->method('isTemplateEditable')->willReturn(true);

		static::assertArrayHasKey('has_action', $this->preparer->prepareTableBody([self::template(owner: 2)], $this->fields(['name']), 1)[0]);
	}

	#[Group('units')]
	public function testOthersGetNoContextMenu(): void
	{
		$this->aclValidatorStub->method('isTemplateEditable')->willReturn(false);

		static::assertArrayNotHasKey('has_action', $this->preparer->prepareTableBody([self::template(owner: 2)], $this->fields(['name']), 3)[0]);
	}

	#[Group('units')]
	public function testTheContextMenuTexts(): void
	{
		static::assertSame(
			['LANG_COMPOSE' => 'templates.composer', 'LANG_SETTINGS' => 'main.settings', 'LANG_TEMPLATE_DELETE' => 'main.delete', 'LANG_TEMPLATE_DELETE_CONFIRM' => 'templates.confirm_delete'],
			$this->preparer->formatContextMenu()
		);
	}
}
