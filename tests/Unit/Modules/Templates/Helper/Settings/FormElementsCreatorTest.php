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

namespace Tests\Unit\Modules\Templates\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Templates\Helper\Settings\FormElementsCreator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\HtmlFields;
use Tests\Support\MemorySession;

/**
 * The creator and the FormBuilder are the real ones: the result is the html of the field.
 */
class FormElementsCreatorTest extends TestCase
{
	private FormBuilder $formBuilder;
	private FormElementsCreator $creator;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn(['public' => 'Public', 'private' => 'Private']);
		$csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());

		$this->formBuilder = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), $csrfToken);
		$this->creator     = new FormElementsCreator($this->formBuilder, $translator);
	}

	/**
	 * @return array<string,string> the attributes of the input of the field
	 */
	private function attributesOf(FieldInterface $field): array
	{
		$attributes = HtmlFields::attributesOf($this->formBuilder, ['field' => $field]);
		static::assertCount(1, $attributes);

		return array_values($attributes)[0];
	}

	#[Group('units')]
	public function testTheNameIsATextWithTheLabelOfTheTemplates(): void
	{
		$field = $this->creator->createNameField('Lobby');

		static::assertSame(FieldType::TEXT, $field->getType());
		static::assertSame('templates.name', $field->getLabel());
		static::assertSame('Lobby', $this->attributesOf($field)['value']);
		static::assertSame('name', $this->attributesOf($field)['name']);
	}

	#[Group('units')]
	public function testAValueWithQuotesCanNotLeaveTheAttribute(): void
	{
		$attributes = $this->attributesOf($this->creator->createNameField('x" onfocus="alert(1)'));

		static::assertSame('x" onfocus="alert(1)', $attributes['value']);
		static::assertArrayNotHasKey('onfocus', $attributes);
	}

	#[Group('units')]
	public function testTheOwnerIsAnAutocompleteWithTheNameOfTheUser(): void
	{
		$field = $this->creator->createUIDField(3, 'bernd', 7);

		static::assertSame(FieldType::AUTOCOMPLETE, $field->getType());
		static::assertSame('UID', $field->getName());
		static::assertSame('main.owner', $field->getLabel());
		$all = json_encode($this->formBuilder->prepareForm(['UID' => $field]), JSON_THROW_ON_ERROR);
		static::assertStringContainsString('bernd', $all);
		static::assertMatchesRegularExpression('/value=.{0,2}3\b/', $all);
	}

	#[Group('units')]
	public function testTheVisibilityIsADropdownWithTheOptionsOfTheMainTranslation(): void
	{
		$field = $this->creator->createVisibilityField('public');

		static::assertSame(FieldType::DROPDOWN, $field->getType());
		static::assertSame('visibility', $field->getName());
		static::assertSame('main.visibility', $field->getLabel());
		$all = json_encode($this->formBuilder->prepareForm(['visibility' => $field]), JSON_THROW_ON_ERROR);
		static::assertStringContainsString('Public', $all);
		static::assertStringContainsString('Private', $all);
	}

	#[Group('units')]
	public function testTheTypeAndTheIdAreHiddenFields(): void
	{
		$type = $this->creator->createHiddenTypeField('canvas');
		$id   = $this->creator->createHiddenTemplateIdField(12);

		static::assertSame(['hidden', 'type', 'canvas'], array_values(array_intersect_key($this->attributesOf($type), array_flip(['type', 'name', 'value']))));
		static::assertSame(['hidden', 'template_id', '12'], array_values(array_intersect_key($this->attributesOf($id), array_flip(['type', 'name', 'value']))));
	}

	#[Group('units')]
	public function testTheTokenFieldHasTheTokenOfTheSession(): void
	{
		$field = $this->creator->createCSRFTokenField();

		static::assertSame(FieldType::CSRF, $field->getType());
		$attributes = $this->attributesOf($field);
		static::assertSame('csrf_token', $attributes['name']);
		static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $attributes['value']);
	}

	#[Group('units')]
	public function testTheFormIsSplitInHiddenAndVisibleFields(): void
	{
		$form = $this->creator->prepareForm(['template_id' => $this->creator->createHiddenTemplateIdField(5), 'name' => $this->creator->createNameField('Lobby')]);

		static::assertCount(1, $form['hidden']);
		static::assertCount(1, $form['visible']);
		static::assertSame('templates.name', $form['visible'][0]['LANG_ELEMENT_LABEL']);
	}
}
