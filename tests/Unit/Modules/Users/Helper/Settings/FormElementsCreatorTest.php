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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Users\Helper\Settings\FormElementsCreator;
use App\Modules\Users\Helper\Settings\Parameters;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class FormElementsCreatorTest extends TestCase
{
	private FormBuilder&MockObject $formBuilderMock;
	private Translator&MockObject $translatorMock;
	private FormElementsCreator $collector;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formBuilderMock = $this->createMock(FormBuilder::class);
		$this->translatorMock  = $this->createMock(Translator::class);

		$this->collector = new FormElementsCreator($this->formBuilderMock, $this->translatorMock);
	}


	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateUserNameField(): void
	{
		$value = 'My Playlist';
		$expectedField = $this->createMock(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->with(Parameters::PARAMETER_USER_NAME, 'main')
			->willReturn('User name');

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::TEXT,
				'id' => Parameters::PARAMETER_USER_NAME,
				'name' => Parameters::PARAMETER_USER_NAME,
				'title' => 'User name',
				'label' => 'User name',
				'value' => $value,
				'rules' => ['required' => true, 'minlength' => 2],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$result = $this->collector->createUserNameField($value);
		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateUserStatusField(): void
	{
		$value = 'active';
		$expectedOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
		$expectedField = $this->createMock(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->with(Parameters::PARAMETER_USER_STATUS, 'users')
			->willReturn('Status');

		$this->translatorMock->expects($this->once())
			->method('translateArrayForOptions')
			->with('status_selects', 'users')
			->willReturn($expectedOptions);

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::DROPDOWN,
				'id' => 'status',
				'name' => 'status',
				'title' => 'Status',
				'label' => 'Status',
				'value' => $value,
				'options' => $expectedOptions,
				'options_zero' => false,
			])
			->willReturn($expectedField);

		$result = $this->collector->createUserStatusField($value);
		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateUserLocaleField(): void
	{
		$value = 'en_US';
		$expectedField = $this->createMock(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->with('locale', 'users')
			->willReturn('User Locale');

		$this->translatorMock->expects($this->once())->method('translateArrayForOptions')
			->with('languages', 'menu')
			->willReturn([
				'en_US' => 'English (United States)',
				'de_DE' => 'Deutsch (Deutschland)',
			]);

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::DROPDOWN,
				'id' => 'locale',
				'name' => 'locale',
				'title' => 'User Locale',
				'label' => 'User Locale',
				'value' => $value,
				'options' => [
					'en_US' => 'English (United States)',
					'de_DE' => 'Deutsch (Deutschland)',
				],
				'options_zero' => false,
			])
			->willReturn($expectedField);

		$result = $this->collector->createUserLocaleField($value);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateClipboardTextField(): void
	{
		$token = 'testToken123';
		$purpose = 'email_verification';
		$expiresAt = '2025-12-31';
		$expectedField = $this->createMock(ClipboardTextField::class);

		$this->translatorMock->expects($this->once())
			->method('translateArrayForOptions')
			->with('purposes_selects', 'profile')
			->willReturn(['email_verification' => 'Email Verification']);

		$this->translatorMock->expects($this->exactly(4))->method('translate')
			->willReturnMap([
				['verification_link', 'profile', [], 'Verification link for %s expires at %s. Please copy it now, as it will only be displayed once.'],
				['copy_to_clipboard', 'main', [], 'Copy to clipboard'],
				['remove', 'main', [], 'Remove'],
				['refresh', 'main', [], 'Refresh']
			]);

		$this->formBuilderMock
			->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::CLIPBOARD_TEXT,
				'id' => $purpose,
				'label' => 'Verification link for Email Verification expires at 2025-12-31. Please copy it now, as it will only be displayed once.',
				'title' => 'Copy to clipboard',
				'value' => 'http://' . $_SERVER['HTTP_HOST'] . '/force-password?token=' . $token
			])
			->willReturn($expectedField);

		$expectedField->expects($this->once())->method('setDeleteTitle')
			->with('Remove');

		$expectedField->expects($this->once())->method('setRefreshTitle')
			->with('Refresh');

		$result = $this->collector->createClipboardTextField($token, $purpose, $expiresAt);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateEmailField(): void
	{
		$value = 'test_email@example.com';
		$expectedField = $this->createMock(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))
			->method('translate')
			->willReturnMap([
				['email', 'users', [], 'Email']
			]);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::EMAIL,
				'id' => 'email',
				'name' => 'email',
				'title' => 'Email',
				'label' => 'Email',
				'value' => $value,
				'rules' => ['required' => true],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$result = $this->collector->createEmailField($value);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateHiddenUIDField(): void
	{
		$value = 42;
		$expectedField = $this->createMock(FieldInterface::class);

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::HIDDEN,
				'id' => 'UID',
				'name' => 'UID',
				'value' => $value,
			])
			->willReturn($expectedField);

		$result = $this->collector->createHiddenUIDField($value);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAddResetPasswordButton(): void
	{
		$translatedButtonName = 'Password Reset';

		$this->translatorMock
			->expects(static::once())
			->method('translate')
			->with('password_reset', 'profile')
			->willReturn($translatedButtonName);

		$result = $this->collector->addResetPasswordButton();

		static::assertSame([
			'ADDITIONAL_BUTTON_TYPE' => 'submit',
			'ADDITIONAL_BUTTON_NAME' => 'resetPassword',
			'LANG_ADDITIONAL_BUTTON' => $translatedButtonName,
		], $result);
	}
}
