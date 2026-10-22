<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Http\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Users\Helper\Settings\FormElementsCreator;
use App\Modules\Users\Helper\Settings\Parameters;
use App\Modules\Users\Helper\Settings\SettingsFormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsFormBuilderTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private Session&MockObject $sessionMock;
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private SettingsFormBuilder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock          = $this->createMock(Parameters::class);
		$this->sessionMock             = $this->createMock(Session::class);
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);

		$this->builder = new SettingsFormBuilder($this->parametersMock, $this->sessionMock, $this->formElementsCreatorMock);
	}

	/**
	 * Without rights only the hidden fields are rendered.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithoutParameters(): void
	{
		$this->parametersMock->expects($this->exactly(4))->method('hasParameter')->willReturn(false);
		$this->sessionMock->expects($this->never())->method($this->anything());
		$csrfTokenField = $this->createStub(FieldInterface::class);
		$this->formElementsCreatorMock->expects($this->never())->method('createUserNameField');
		$this->formElementsCreatorMock->expects($this->never())->method('createHiddenUIDField');
		$this->formElementsCreatorMock->expects($this->once())->method('createCSRFTokenField')->willReturn($csrfTokenField);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with([BaseEditParameters::PARAMETER_CSRF_TOKEN => $csrfTokenField])
			->willReturn(['hidden' => [], 'visible' => []]);

		static::assertSame(['hidden' => [], 'visible' => []], $this->builder->buildForm([]));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithUserAndTokens(): void
	{
		$user = [
			Parameters::PARAMETER_USER_NAME   => 'heidi',
			Parameters::PARAMETER_USER_EMAIL  => 'heidi@example.com',
			Parameters::PARAMETER_USER_STATUS => 1,
			Parameters::PARAMETER_USER_LOCALE => 'de_DE',
			Parameters::PARAMETER_USER_ID     => 12,
			'tokens' => [
				['token' => 'a', 'purpose' => 'autologin', 'expires_at' => '2030-01-01'],
				['token' => 'b', 'purpose' => 'password_reset', 'expires_at' => '2030-01-02'],
				['token' => 'c', 'purpose' => 'initial_password', 'expires_at' => '2030-01-03'],
			]
		];
		$this->parametersMock->expects($this->exactly(4))->method('hasParameter')->willReturn(true);

		// only the freshly created token is in the session, it is shown once and then removed
		$this->sessionMock->expects($this->exactly(2))->method('exists')
			->willReturnMap([['password_reset_token', true], ['initial_password_token', false]]);
		$this->sessionMock->expects($this->once())->method('get')->with('password_reset_token')->willReturn('beef');
		$this->sessionMock->expects($this->once())->method('delete')->with('password_reset_token');

		$field          = $this->createStub(FieldInterface::class);
		$resetField     = $this->createStub(ClipboardTextField::class);
		$initialField   = $this->createStub(ClipboardTextField::class);
		$this->formElementsCreatorMock->expects($this->once())->method('createUserNameField')->with('heidi')->willReturn($field);
		$this->formElementsCreatorMock->expects($this->once())->method('createEmailField')->with('heidi@example.com')->willReturn($field);
		$this->formElementsCreatorMock->expects($this->once())->method('createUserStatusField')->with('1')->willReturn($field);
		$this->formElementsCreatorMock->expects($this->once())->method('createUserLocaleField')->with('de_DE')->willReturn($field);
		$this->formElementsCreatorMock->expects($this->exactly(2))->method('createClipboardTextField')
			->willReturnMap([
				['beef', 'password_reset', '2030-01-02', $resetField],
				['', 'initial_password', '2030-01-03', $initialField]
			]);
		$this->formElementsCreatorMock->expects($this->once())->method('createHiddenUIDField')->with(12)->willReturn($field);
		$this->formElementsCreatorMock->expects($this->once())->method('createCSRFTokenField')->willReturn($field);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with([
				'username'               => $field,
				'email'                  => $field,
				'status'                 => $field,
				'locale'                 => $field,
				'token_password_reset'   => $resetField,
				'token_initial_password' => $initialField,
				'UID'                    => $field,
				'csrf_token'             => $field
			])
			->willReturn(['hidden' => [], 'visible' => []]);

		$this->builder->buildForm($user);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildButtons(): void
	{
		$this->parametersMock->expects($this->never())->method($this->anything());
		$this->sessionMock->expects($this->never())->method($this->anything());
		$button = ['ADDITIONAL_BUTTON_NAME' => 'resetPassword'];
		$this->formElementsCreatorMock->expects($this->once())->method('addResetPasswordButton')->willReturn($button);

		static::assertSame([$button], $this->builder->buildButtons());
	}
}
