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

namespace Tests\Unit\Modules\Account\Helper\Password;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Account\Helper\Password\TemplatePreparer;
use App\Shared\User\TokenPurposes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The FormTemplatePreparer is the real one: the result is the data of the page.
 */
class TemplatePreparerTest extends TestCase
{
	private TemplatePreparer $preparer;

	/** @var array{hidden: list<array<string,string>>, visible: list<array<string,string>>} */
	private array $form = [
		'hidden'  => [['HIDDEN_HTML_ELEMENT' => '<input type="hidden" name="csrf_token">']],
		'visible' => [['HTML_ELEMENT_ID' => 'password']],
	];

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')
			->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key . ($key === 'initial_password_for' || $key === 'edit_password_for' ? ' %s' : ''));

		$this->preparer = new TemplatePreparer($translator, new FormTemplatePreparer());
	}

	/**
	 * @param array<string,mixed> $page
	 * @return array<string,mixed>
	 */
	private function data(array $page): array
	{
		$layout = $page['this_layout'];
		static::assertIsArray($layout);
		static::assertSame('account/edit', $layout['template']);
		static::assertIsArray($layout['data']);

		return $layout['data'];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testThePageToChangeTheOwnPassword(): void
	{
		$page = $this->preparer->preparePasswordForm($this->form);
		$data = $this->data($page);

		static::assertSame('account.edit_password', $page['main_layout']['LANG_PAGE_TITLE']);
		static::assertSame(['/css/account/password.css'], $page['main_layout']['additional_css']);
		static::assertSame(['/js/account/password/init.js'], $page['main_layout']['footer_modules']);
		static::assertSame('account.edit_password', $data['LANG_PAGE_HEADER']);
		static::assertSame('/account/password', $data['FORM_ACTION']);
		static::assertSame('account.password_explanation', $data['LANG_FORM_EXPLANATION']);
		static::assertSame($this->form['hidden'], $data['element_hidden']);
		static::assertSame($this->form['visible'], $data['form_element']);
		static::assertSame('main.save', $data['form_button'][0]['LANG_ELEMENT_BUTTON']);
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function purposeProvider(): array
	{
		return [
			'first password'  => [TokenPurposes::INITIAL_PASSWORD->value, 'account.initial_password_for heidi'],
			'password reset'  => [TokenPurposes::PASSWORD_RESET->value, 'account.edit_password_for heidi'],
			'any other token' => [TokenPurposes::EMAIL_VERIFICATION->value, 'account.edit_password_for heidi'],
		];
	}

	/**
	 * The form of a link posts to the route which works without a login and names the user.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('purposeProvider')]
	public function testThePageOfALinkNamesTheUser(string $purpose, string $title): void
	{
		$page = $this->preparer->prepareForcedPasswordForm($this->form, ['username' => 'heidi', 'purpose' => $purpose]);
		$data = $this->data($page);

		static::assertSame($title, $page['main_layout']['LANG_PAGE_TITLE']);
		static::assertSame($title, $data['LANG_PAGE_HEADER']);
		static::assertSame('/force-password', $data['FORM_ACTION']);
		static::assertSame($this->form['hidden'], $data['element_hidden']);
	}

	/**
	 * A name with signs of sprintf must come out as it is: it is an argument, not part of the format.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheNameOfTheUserIsNoFormat(): void
	{
		$page = $this->preparer->prepareForcedPasswordForm($this->form, ['username' => '%s%d<b>', 'purpose' => TokenPurposes::PASSWORD_RESET->value]);

		static::assertSame('account.edit_password_for %s%d<b>', $page['main_layout']['LANG_PAGE_TITLE']);
	}
}
