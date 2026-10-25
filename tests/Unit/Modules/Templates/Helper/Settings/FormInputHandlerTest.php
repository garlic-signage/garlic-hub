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
use App\Framework\Security\CsrfToken;
use App\Framework\Validation\Sanitizer;
use App\Modules\Templates\Helper\Settings\FormInputHandler;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Templates\Helper\Settings\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The input of the template settings form: parsed by the real parameters and checked by the real validator.
 */
class FormInputHandlerTest extends TestCase
{
	private SettingsParametersPolicy $policy;
	private FormInputHandler $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturnMap([['types_selects', 'templates', ['canvas' => 'Canvas']]]);
		$csrfToken = $this->createStub(CsrfToken::class);
		$csrfToken->method('validateToken')->willReturnCallback(static fn(string $token): bool => $token === 'good');

		$parameters   = new Parameters(new Sanitizer(), new MemorySession());
		$this->policy  = new SettingsParametersPolicy($parameters);
		$this->handler = new FormInputHandler($parameters, new Validator($translator, $parameters, $csrfToken));
	}

	#[Group('units')]
	public function testAValidNewTemplateHasNoErrorsAndIsParsed(): void
	{
		$this->policy->addCreateFormElements();

		$errors = $this->handler->validateCreate(['csrf_token' => 'good', 'name' => 'Lobby', 'visibility' => 'public', 'type' => 'canvas', 'UID' => '3']);

		static::assertSame([], $errors);
		static::assertSame(['csrf_token' => 'good', 'name' => 'Lobby', 'UID' => 3, 'visibility' => 'public', 'type' => 'canvas'], $this->handler->getParsed());
	}

	#[Group('units')]
	public function testAnEditNeedsNoType(): void
	{
		$this->policy->addEditFormElements();

		static::assertSame([], $this->handler->validateEdit(['csrf_token' => 'good', 'name' => 'Lobby']));
	}

	#[Group('units')]
	public function testAWrongTokenAndAMissingNameAreErrors(): void
	{
		$this->policy->addEditFormElements();

		static::assertSame(['security.csrf_token_mismatch', 'templates.name_noexists'], $this->handler->validateEdit(['csrf_token' => 'wrong', 'name' => '']));
	}

	#[Group('units')]
	public function testANewTemplateNeedsAKnownType(): void
	{
		$this->policy->addCreateFormElements();

		static::assertSame(['templates.template_type_noexists'], $this->handler->validateCreate(['csrf_token' => 'good', 'name' => 'Lobby', 'type' => 'fabric.js']));
	}

	#[Group('units')]
	public function testWithoutATypeANewTemplateIsACanvas(): void
	{
		$this->policy->addCreateFormElements();

		static::assertSame([], $this->handler->validateCreate(['csrf_token' => 'good', 'name' => 'Lobby']));
		static::assertSame('canvas', $this->handler->getParsed()['type'] ?? null);
	}

	#[Group('units')]
	public function testValuesOfTheWrongTypeAreErrorsNotExceptions(): void
	{
		$this->policy->addCreateFormElements();

		static::assertSame(['security.csrf_token_mismatch', 'templates.name_noexists'], $this->handler->validateCreate(['csrf_token' => ['good'], 'name' => ['Lobby'], 'type' => 'canvas']));
	}
}
