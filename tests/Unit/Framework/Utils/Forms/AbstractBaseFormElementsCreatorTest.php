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

namespace Tests\Unit\Framework\Utils\Forms;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Forms\AbstractBaseFormElementsCreator;
use App\Framework\Utils\Html\CsrfTokenField;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class ConcreteFormElementsCreator extends AbstractBaseFormElementsCreator
{
	public function formBuilder(): FormBuilder
	{
		return $this->formBuilder;
	}
}

class AbstractBaseFormElementsCreatorTest extends TestCase
{
	private CsrfToken $csrfToken;
	private ConcreteFormElementsCreator $creator;

	/**
	 * @throws EnvironmentIsBrokenException
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());
		$this->creator   = new ConcreteFormElementsCreator(
			new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), $this->csrfToken),
			$this->createStub(Translator::class)
		);
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheCsrfFieldIsNamedLikeTheParameterAndCarriesTheToken(): void
	{
		$field = $this->creator->createCSRFTokenField();

		static::assertInstanceOf(CsrfTokenField::class, $field);
		static::assertSame(FieldType::CSRF, $field->getType());
		static::assertSame('csrf_token', $field->getId());
		static::assertSame('csrf_token', $field->getName());
		static::assertSame($this->csrfToken->getToken(), $field->getValue());
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareFormPutsTheCsrfFieldIntoTheHiddenFields(): void
	{
		$form = [
			'csrf'  => $this->creator->createCSRFTokenField(),
			'title' => $this->creator->formBuilder()->createField(['id' => 'title', 'type' => FieldType::TEXT, 'name' => 'title', 'label' => 'Title']),
		];

		$result = $this->creator->prepareForm($form);

		static::assertSame(
			[['HIDDEN_HTML_ELEMENT' => '<input type="hidden" name="csrf_token" id="csrf_token" value="' . $this->csrfToken->getToken() . '">']],
			$result['hidden']
		);
		static::assertSame(['title'], array_column($result['visible'], 'HTML_ELEMENT_ID'));
	}

	#[Group('units')]
	public function testPrepareFormWithoutFields(): void
	{
		static::assertSame(['hidden' => [], 'visible' => []], $this->creator->prepareForm([]));
	}
}
