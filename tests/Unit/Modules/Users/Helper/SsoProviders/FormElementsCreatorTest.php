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

namespace Tests\Unit\Modules\Users\Helper\SsoProviders;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Users\Helper\SsoProviders\FormElementsCreator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\HtmlFields;
use Tests\Support\MemorySession;

/**
 * The form of an SSO provider with the real FormBuilder: the result is the html a browser gets.
 */
class FormElementsCreatorTest extends TestCase
{
	private FormElementsCreator $creator;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$formBuilder = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession()));

		$this->creator = new FormElementsCreator($formBuilder, $translator);
	}

	/**
	 * @return array{provider_id:int, name:string, issuer:string, client_id:string, allowed_domains:string, enabled:bool, has_secret:bool}
	 */
	private function values(bool $hasSecret, bool $enabled = true): array
	{
		return ['provider_id' => 5, 'name' => 'Entra', 'issuer' => 'https://login.example.test', 'client_id' => 'cid', 'allowed_domains' => 'example.de', 'enabled' => $enabled, 'has_secret' => $hasSecret];
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	private function attributes(bool $hasSecret, bool $enabled = true): array
	{
		$attributes = HtmlFields::fromPrepared($this->creator->buildForm($this->values($hasSecret, $enabled)));
		// the token changes with every form
		unset($attributes['csrf_token']['value']);

		return $attributes;
	}

	#[Group('units')]
	public function testTheFieldsOfAStoredProvider(): void
	{
		static::assertSame([
			'name'            => ['type' => 'text', 'name' => 'name', 'id' => 'name', 'value' => 'Entra', 'label' => 'users.sso_name', 'required' => 'required', 'maxlength' => '100', 'aria-describedby' => 'error_name'],
			'issuer'          => ['type' => 'text', 'name' => 'issuer', 'id' => 'issuer', 'value' => 'https://login.example.test', 'label' => 'users.sso_issuer', 'placeholder' => 'https://accounts.google.com', 'required' => 'required', 'aria-describedby' => 'error_issuer'],
			'client_id'       => ['type' => 'text', 'name' => 'client_id', 'id' => 'client_id', 'value' => 'cid', 'label' => 'users.sso_client_id', 'required' => 'required', 'aria-describedby' => 'error_client_id'],
			// the secret is never shown: a stored one needs not to be entered again
			'client_secret'   => ['type' => 'password', 'name' => 'client_secret', 'id' => 'client_secret', 'value' => '', 'label' => 'users.sso_client_secret', 'autocomplete' => 'new-password', 'placeholder' => 'users.sso_secret_stored', 'aria-describedby' => 'error_client_secret'],
			'allowed_domains' => ['type' => 'text', 'name' => 'allowed_domains', 'id' => 'allowed_domains', 'value' => 'example.de', 'label' => 'users.sso_allowed_domains', 'placeholder' => 'example.com, example.org', 'aria-describedby' => 'error_allowed_domains'],
			'enabled'         => ['type' => 'checkbox', 'id' => 'enabled', 'name' => 'enabled', 'checked' => 'checked', 'value' => '1', 'aria-describedby' => 'error_enabled'],
			'provider_id'     => ['type' => 'hidden', 'name' => 'provider_id', 'id' => 'provider_id', 'value' => '5'],
			'csrf_token'      => ['type' => 'hidden', 'name' => 'csrf_token', 'id' => 'csrf_token'],
		], $this->attributes(true));
	}

	#[Group('units')]
	public function testANewProviderNeedsTheSecret(): void
	{
		$secret = $this->attributes(false)['client_secret'];

		static::assertSame('required', $secret['required'] ?? null);
		static::assertSame('', $secret['placeholder'] ?? null);
	}

	#[Group('units')]
	public function testAProviderWhichIsOffIsNotChecked(): void
	{
		static::assertArrayNotHasKey('checked', $this->attributes(true, false)['enabled']);
	}

	#[Group('units')]
	public function testTheLabelsOfTheForm(): void
	{
		$visible = $this->creator->buildForm($this->values(true))['visible'];

		static::assertSame(
			['users.sso_name', 'users.sso_issuer', 'users.sso_client_id', 'users.sso_client_secret', 'users.sso_allowed_domains', 'users.sso_enabled'],
			array_column($visible, 'LANG_ELEMENT_LABEL')
		);
		static::assertStringEndsWith('> users.sso_enabled', $visible[5]['HTML_ELEMENT']);
	}

	#[Group('units')]
	public function testAStoredProviderCanBeDeletedAfterAConfirmation(): void
	{
		static::assertSame([[
			'ADDITIONAL_BUTTON_TYPE'    => 'submit',
			'ADDITIONAL_BUTTON_NAME'    => 'delete',
			'LANG_ADDITIONAL_BUTTON'    => 'main.delete',
			'ADDITIONAL_BUTTON_CONFIRM' => 'users.sso_delete_confirm'
		]], $this->creator->buildButtons(true));
	}

	#[Group('units')]
	public function testANewProviderHasNothingToDelete(): void
	{
		static::assertSame([], $this->creator->buildButtons(false));
	}
}
