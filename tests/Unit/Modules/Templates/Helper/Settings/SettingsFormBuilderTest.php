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
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Validation\Sanitizer;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Helper\Settings\FormElementsCreator;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Helper\Settings\SettingsFormBuilder;
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\HtmlFields;
use Tests\Support\MemorySession;

/**
 * The form of the template settings with the real parameters, creator and FormBuilder: the result is the html of the fields.
 */
class SettingsFormBuilderTest extends TestCase
{
	private SettingsParametersPolicy $policy;
	private SettingsFormBuilder $builder;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn(['public' => 'Public', 'private' => 'Private']);
		$userSession = $this->createStub(UserSession::class);
		$userSession->method('getUID')->willReturn(7);
		$userSession->method('getUsername')->willReturn('heidi');
		$session = new MemorySession();

		$formBuilder  = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session));
		$parameters   = new Parameters(new Sanitizer(), $session);
		$this->policy  = new SettingsParametersPolicy($parameters);
		$this->builder = new SettingsFormBuilder($parameters, $userSession, new FormElementsCreator($formBuilder, $translator));
	}

	/**
	 * @param array<string,mixed> $values
	 * @return array<string,array<string,string>> the attributes of the fields by name
	 */
	private function build(array $values): array
	{
		return HtmlFields::fromPrepared($this->builder->buildForm($values));
	}

	#[Group('units')]
	public function testTheFormOfANewTemplateIsACanvasOwnedByTheLoggedInUser(): void
	{
		$this->policy->addCreateFormElements();

		$fields = $this->build([]);

		static::assertSame(['name', 'UID_search', 'visibility', 'type', 'csrf_token'], array_keys($fields));
		static::assertSame(['', 'canvas'], [$fields['name']['value'], $fields['type']['value']]);
		static::assertStringContainsString('heidi', json_encode($this->builder->buildForm([]), JSON_THROW_ON_ERROR));
	}

	#[Group('units')]
	public function testTheFormOfAnExistingTemplateHasItsValuesAndItsId(): void
	{
		$this->policy->addEditFormElements();
		$template = ['template_id' => 12, 'name' => 'Lobby', 'UID' => 3, 'username' => 'owner', 'visibility' => 'public'];

		$fields = $this->build($template);

		static::assertSame(['name', 'UID_search', 'visibility', 'template_id', 'csrf_token'], array_keys($fields));
		static::assertSame(['Lobby', '12'], [$fields['name']['value'], $fields['template_id']['value']]);
		static::assertStringContainsString('owner', json_encode($this->builder->buildForm($template), JSON_THROW_ON_ERROR));
	}

	#[Group('units')]
	public function testWithoutOwnerAndVisibilityThereAreNoSuchFields(): void
	{
		static::assertSame(['name', 'type', 'csrf_token'], array_keys($this->build([])));
	}
}
