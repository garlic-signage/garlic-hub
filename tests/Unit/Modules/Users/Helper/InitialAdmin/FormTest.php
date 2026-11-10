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

namespace Tests\Unit\Modules\Users\Helper\InitialAdmin;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\PasswordField;
use App\Modules\Users\Helper\InitialAdmin\FormElementsCreator;
use App\Modules\Users\Helper\InitialAdmin\SettingsFormBuilder;
use App\Modules\Users\Helper\InitialAdmin\TemplatePreparer;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\HtmlFields;
use Tests\Support\MemorySession;

/**
 * The form of the first admin with the real FormBuilder: the fields, the form and the page.
 */
class FormTest extends TestCase
{
	private Translator $translator;
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
		$translator->method('translateArrayForOptions')->willReturn(['en_US' => 'English', 'de_DE' => 'Deutsch']);
		$this->translator  = $translator;
		$this->formBuilder = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession()));
		$this->creator     = new FormElementsCreator($this->formBuilder, $translator);
	}

	/**
	 * @return array{type: FieldType, id: string, name: string, title: string, label: string, value: string|int, rules: array<string,mixed>}
	 */
	private function describe(FieldInterface $field): array
	{
		return ['type' => $field->getType(), 'id' => $field->getId(), 'name' => $field->getName(), 'title' => $field->getTitle(), 'label' => $field->getLabel(), 'value' => $field->getValue(), 'rules' => $field->getValidationRules()];
	}

	#[Group('units')]
	public function testTheNameAndTheEmail(): void
	{
		static::assertSame(
			['type' => FieldType::TEXT, 'id' => 'username', 'name' => 'username', 'title' => 'main.username', 'label' => 'main.username', 'value' => 'root', 'rules' => ['required' => true, 'minlength' => 2]],
			$this->describe($this->creator->createUserNameField('root'))
		);
		static::assertSame(
			['type' => FieldType::EMAIL, 'id' => 'email', 'name' => 'email', 'title' => 'users.email', 'label' => 'users.email', 'value' => 'root@example.com', 'rules' => ['required' => true]],
			$this->describe($this->creator->createEmailField('root@example.com'))
		);
	}

	/**
	 * The password of the first admin has at least 8 characters and the pattern of the config.
	 */
	#[Group('units')]
	public function testThePasswordsHaveAMinimalLength(): void
	{
		$password = $this->creator->createPasswordField('', '(?=.*\d).{8,}');

		static::assertInstanceOf(PasswordField::class, $password);
		static::assertSame(
			['type' => FieldType::PASSWORD, 'id' => 'password', 'name' => 'password', 'title' => 'users.password_explanation', 'label' => 'users.password', 'value' => '', 'rules' => ['required' => true, 'minlength' => 8]],
			$this->describe($password)
		);
		static::assertSame('(?=.*\d).{8,}', $password->getPattern());
		static::assertSame(
			['type' => FieldType::PASSWORD, 'id' => 'password_confirm', 'name' => 'password_confirm', 'title' => 'users.password_explanation', 'label' => 'users.password_confirm', 'value' => '', 'rules' => ['required' => true, 'minlength' => 8]],
			$this->describe($this->creator->createPasswordConfirmField(''))
		);
	}

	#[Group('units')]
	public function testTheLocaleOffersTheLanguagesWithoutAnEmptyChoice(): void
	{
		$field = $this->creator->createUserLocaleField('de_DE');

		static::assertInstanceOf(DropdownField::class, $field);
		static::assertSame(
			['type' => FieldType::DROPDOWN, 'id' => 'locale', 'name' => 'locale', 'title' => 'users.locale', 'label' => 'users.locale', 'value' => 'de_DE', 'rules' => []],
			$this->describe($field)
		);
		static::assertSame(['en_US' => 'English', 'de_DE' => 'Deutsch'], $field->getOptions());
		static::assertFalse($field->isOptionsZero());
	}

	#[Group('units')]
	public function testTheFormKeepsTheInputButNotThePasswords(): void
	{
		$form = (new SettingsFormBuilder($this->creator))->buildForm(['username' => 'root', 'email' => 'root@example.com', 'locale' => 'de_DE'], '.{8,}');

		$fields = HtmlFields::fromPrepared($form);
		static::assertSame(['username', 'email', 'password', 'password_confirm', 'locale', 'csrf_token'], array_keys($fields));
		static::assertSame(['root', 'root@example.com', '', ''], [$fields['username']['value'], $fields['email']['value'], $fields['password']['value'], $fields['password_confirm']['value']]);
		static::assertMatchesRegularExpression('/<option value="de_DE" selected>/', implode('', array_column($form['visible'], 'HTML_ELEMENT')));
	}

	#[Group('units')]
	public function testAnEmptyFormIsInEnglish(): void
	{
		$form = (new SettingsFormBuilder($this->creator))->buildForm([], '.{8,}');

		static::assertMatchesRegularExpression('/<option value="en_US" selected>/', implode('', array_column($form['visible'], 'HTML_ELEMENT')));
	}

	#[Group('units')]
	public function testThePageHasTheStylesAndTheScriptOfThePassword(): void
	{
		$page = (new TemplatePreparer($this->translator, new FormTemplatePreparer()))->prepare(['hidden' => [], 'visible' => []]);

		static::assertSame(
			['LANG_PAGE_TITLE' => 'users.create_admin', 'additional_css' => ['/css/users/edit.css', '/css/account/password.css'], 'footer_modules' => ['/js/account/password/init.js']],
			$page['main_layout']
		);
		static::assertSame(['users/edit', '/create-initial', 'main.save'], [$page['this_layout']['template'], $page['this_layout']['data']['FORM_ACTION'], $page['this_layout']['data']['form_button'][0]['LANG_ELEMENT_BUTTON']]);
	}
}
