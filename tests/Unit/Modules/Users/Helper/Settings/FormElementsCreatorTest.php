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
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Users\Helper\Settings\FormElementsCreator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The fields of the form of a user, built by the real FormBuilder.
 */
class FormElementsCreatorTest extends TestCase
{
	private FormElementsCreator $creator;
	/** @var array<string,mixed> */
	private array $server;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->server = $_SERVER;
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key === 'verification_link' ? '%s until %s' : $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturnCallback(static fn(string $key): array => match ($key)
		{
			'status_selects'   => [0 => 'Deleted', 1 => 'Blocked', 2 => 'Not verified', 3 => 'Registered', 9 => 'Admin'],
			'languages'        => ['en_US' => 'English', 'de_DE' => 'Deutsch'],
			'purposes_selects' => ['password_reset' => 'Password reset'],
			default            => []
		});
		$formBuilder = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession()));

		$this->creator = new FormElementsCreator($formBuilder, $translator);
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;
		parent::tearDown();
	}

	/**
	 * @return array{type: FieldType, id: string, name: string, title: string, label: string, value: string|int, rules: array<string,mixed>}
	 */
	private function describe(FieldInterface $field): array
	{
		return ['type' => $field->getType(), 'id' => $field->getId(), 'name' => $field->getName(), 'title' => $field->getTitle(), 'label' => $field->getLabel(), 'value' => $field->getValue(), 'rules' => $field->getValidationRules()];
	}

	#[Group('units')]
	public function testTheUsername(): void
	{
		static::assertSame(
			['type' => FieldType::TEXT, 'id' => 'username', 'name' => 'username', 'title' => 'main.username', 'label' => 'main.username', 'value' => 'bernd', 'rules' => ['required' => true, 'minlength' => 2]],
			$this->describe($this->creator->createUserNameField('bernd'))
		);
	}

	#[Group('units')]
	public function testTheEmail(): void
	{
		static::assertSame(
			['type' => FieldType::EMAIL, 'id' => 'email', 'name' => 'email', 'title' => 'users.email', 'label' => 'users.email', 'value' => 'bernd@example.com', 'rules' => ['required' => true]],
			$this->describe($this->creator->createEmailField('bernd@example.com'))
		);
	}

	/**
	 * A user is deleted with the delete button: deleted is no status to choose, and there is no empty choice.
	 */
	#[Group('units')]
	public function testTheStatusOffersEveryStatusButDeleted(): void
	{
		$field = $this->creator->createUserStatusField('3');

		static::assertInstanceOf(DropdownField::class, $field);
		static::assertSame(
			['type' => FieldType::DROPDOWN, 'id' => 'status', 'name' => 'status', 'title' => 'users.status', 'label' => 'users.status', 'value' => '3', 'rules' => []],
			$this->describe($field)
		);
		static::assertSame([1 => 'Blocked', 2 => 'Not verified', 3 => 'Registered', 9 => 'Admin'], $field->getOptions());
		static::assertFalse($field->isOptionsZero());
	}

	#[Group('units')]
	public function testTheLocaleOffersTheLanguages(): void
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
	public function testTheLinkOfATokenCanBeCopiedRemovedAndRefreshed(): void
	{
		$_SERVER['HTTP_HOST'] = 'hub.example.com';
		$_SERVER['HTTPS']     = 'on';

		$field = $this->creator->createClipboardTextField('abc123', 'password_reset', '2026-12-01 10:00');

		static::assertSame(
			['type' => FieldType::CLIPBOARD_TEXT, 'id' => 'password_reset', 'name' => 'password_reset', 'title' => 'main.copy_to_clipboard', 'label' => 'Password reset until 2026-12-01 10:00', 'value' => 'https://hub.example.com/force-password?token=abc123', 'rules' => []],
			$this->describe($field)
		);
		static::assertSame(['main.remove', 'main.refresh'], [$field->getDeleteTitle(), $field->getRefreshTitle()]);
	}

	#[Group('units')]
	public function testWithoutTlsTheLinkIsHttp(): void
	{
		$_SERVER['HTTP_HOST'] = 'hub.example.com';
		unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

		static::assertSame('http://hub.example.com/force-password?token=abc', $this->creator->createClipboardTextField('abc', 'password_reset', '')->getValue());
	}

	#[Group('units')]
	public function testTheHiddenUid(): void
	{
		$field = $this->creator->createHiddenUIDField(7);

		static::assertSame([FieldType::HIDDEN, 'UID', 'UID', '7'], [$field->getType(), $field->getId(), $field->getName(), $field->getValue()]);
	}

	#[Group('units')]
	public function testTheButtonsOfTheForm(): void
	{
		static::assertSame(['ADDITIONAL_BUTTON_TYPE' => 'submit', 'ADDITIONAL_BUTTON_NAME' => 'resetPassword', 'LANG_ADDITIONAL_BUTTON' => 'users.password_reset'], $this->creator->addResetPasswordButton());
		static::assertSame(['ADDITIONAL_BUTTON_TYPE' => 'submit', 'ADDITIONAL_BUTTON_NAME' => 'resetTwoFactor', 'LANG_ADDITIONAL_BUTTON' => 'users.two_factor_reset'], $this->creator->addResetTwoFactorButton());
		static::assertSame(['ADDITIONAL_BUTTON_TYPE' => 'submit', 'ADDITIONAL_BUTTON_NAME' => 'switchToSso', 'LANG_ADDITIONAL_BUTTON' => 'users.sso_switch'], $this->creator->addSwitchToSsoButton());
	}
}
