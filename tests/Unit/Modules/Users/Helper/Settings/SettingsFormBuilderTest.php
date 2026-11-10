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

namespace Tests\Unit\Modules\Users\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Validation\Sanitizer;
use App\Modules\Users\Helper\Settings\FormElementsCreator;
use App\Modules\Users\Helper\Settings\Parameters;
use App\Modules\Users\Helper\Settings\SettingsFormBuilder;
use App\Modules\Users\Helper\Settings\TemplatePreparer;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\HtmlFields;
use Tests\Support\MemorySession;

/**
 * The form of a user with the real parameters, fields and FormBuilder.
 */
class SettingsFormBuilderTest extends TestCase
{
	private Translator $translator;
	private Parameters $parameters;
	private MemorySession $session;
	private SettingsFormBuilder $builder;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturnCallback(static fn(string $key): array => match ($key)
		{
			'status_selects'   => [0 => 'Deleted', 1 => 'Blocked', 2 => 'Not verified', 3 => 'Registered', 9 => 'Admin'],
			'languages'        => ['en_US' => 'English', 'de_DE' => 'Deutsch'],
			'purposes_selects' => ['password_reset' => 'Password reset', 'initial_password' => 'Initial password'],
			default            => []
		});
		$this->translator = $translator;
		$this->session    = new MemorySession();
		$this->parameters = new Parameters(new Sanitizer(), $this->session);
		$formBuilder      = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $this->session));
		$this->builder    = new SettingsFormBuilder($this->parameters, $this->session, new FormElementsCreator($formBuilder, $translator));
	}

	/**
	 * @param array{hidden:list<array<string,string>>, visible: list<array<string,string>>} $form
	 */
	private function html(array $form): string
	{
		return implode('', array_column($form['visible'], 'HTML_ELEMENT'));
	}

	#[Group('units')]
	public function testTheParametersDecideTheFields(): void
	{
		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_USER_ID));

		static::assertSame(['csrf_token'], array_keys(HtmlFields::fromPrepared($this->builder->buildForm([]))));

		$this->parameters->addUserName();
		$this->parameters->addUserEmail();
		$this->parameters->addUserStatus();
		$this->parameters->addUserLocale();
		static::assertSame(['username', 'email', 'status', 'locale', 'UID', 'csrf_token'], array_keys(HtmlFields::fromPrepared($this->builder->buildForm(['UID' => 3]))));
	}

	/**
	 * A new user is not verified until he has set his password.
	 */
	#[Group('units')]
	public function testANewUserIsNotVerifiedAndEnglish(): void
	{
		$this->parameters->addUserStatus();
		$this->parameters->addUserLocale();

		$html = $this->html($this->builder->buildForm([]));

		static::assertStringContainsString('<option value="2" selected>', $html);
		static::assertSame(1, substr_count($html, ' selected>') - 1);
		static::assertStringContainsString('<option value="en_US" selected>', $html);
	}

	#[Group('units')]
	public function testTheFormShowsTheStatusAndTheLocaleOfTheUser(): void
	{
		$this->parameters->addUserStatus();
		$this->parameters->addUserLocale();

		$html = $this->html($this->builder->buildForm(['status' => 3, 'locale' => 'de_DE']));

		static::assertStringContainsString('<option value="3" selected>', $html);
		static::assertStringNotContainsString('<option value="2" selected>', $html);
		static::assertStringContainsString('<option value="de_DE" selected>', $html);
		static::assertStringNotContainsString('<option value="en_US" selected>', $html);
	}

	/**
	 * A link to set a password is shown once with its token, the token of a remembered login is no link.
	 */
	#[Group('units')]
	public function testTheLinksOfTheTokensAreShownOnce(): void
	{
		$this->session->set('password_reset_token', 'abc123');
		$tokens = [
			['token' => 'x', 'UID' => 3, 'purpose' => 'password_reset', 'expires_at' => '2026-12-01', 'used_at' => null],
			['token' => 'y', 'UID' => 3, 'purpose' => 'autologin', 'expires_at' => '2026-12-01', 'used_at' => null],
			['token' => 'z', 'UID' => 3, 'purpose' => 'initial_password', 'expires_at' => '2026-12-02', 'used_at' => null],
		];

		$form = $this->builder->buildForm(['tokens' => $tokens]);

		static::assertSame(['password_reset', 'initial_password'], array_column($form['visible'], 'HTML_ELEMENT_ID'));
		static::assertStringEndsWith('/force-password?token=abc123', HtmlFields::fromPrepared($form)['password_reset']['value']);
		static::assertStringEndsWith('/force-password?token=', HtmlFields::fromPrepared($form)['initial_password']['value']);
		static::assertFalse($this->session->exists('password_reset_token'));
	}

	#[Group('units')]
	public function testTheButtonsOfTheUser(): void
	{
		static::assertSame(['resetPassword'], array_column($this->builder->buildButtons(false), 'ADDITIONAL_BUTTON_NAME'));
		static::assertSame(['resetPassword', 'resetTwoFactor'], array_column($this->builder->buildButtons(true), 'ADDITIONAL_BUTTON_NAME'));
		static::assertSame(['resetPassword', 'switchToSso'], array_column($this->builder->buildButtons(false, true), 'ADDITIONAL_BUTTON_NAME'));
	}

	#[Group('units')]
	public function testThePagesOfTheForm(): void
	{
		$preparer = new TemplatePreparer($this->translator, new FormTemplatePreparer());

		$new  = $preparer->prepareCreateSettings(['hidden' => [], 'visible' => []]);
		$edit = $preparer->prepareEditSettings(['hidden' => [], 'visible' => []], 'bernd');

		static::assertSame(['LANG_PAGE_TITLE' => 'users.core_data: users.add', 'additional_css' => ['/css/users/edit.css'], 'footer_modules' => ['/js/users/edit/init.js']], $new['main_layout']);
		static::assertSame('users.core_data: bernd', $edit['main_layout']['LANG_PAGE_TITLE']);
		static::assertSame(['users/edit', '/users/edit', 'main.save'], [$edit['this_layout']['template'], $edit['this_layout']['data']['FORM_ACTION'], $edit['this_layout']['data']['form_button'][0]['LANG_ELEMENT_BUTTON']]);
	}
}
