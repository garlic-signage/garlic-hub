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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Playlists\Helper\Settings\FormElementsCreator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
	 */
	#[Group('units')]
	public function testPrepareForm(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$fieldInterfaceMock1 = $this->createStub(FieldInterface::class);
		$fieldInterfaceMock2 = $this->createStub(FieldInterface::class);

		$formData = ['field1' => $fieldInterfaceMock1, 'field2' => $fieldInterfaceMock2];
		$preparedForm = [
			'hidden' => [
				['type' => 'hidden', 'id' => 'field1', 'name' => 'field1'],
				['type' => 'hidden', 'id' => 'field2', 'name' => 'field2']
			],
			'visible' => [
				['type' => 'text', 'id' => 'field3', 'name' => 'field3'],
				['type' => 'number', 'id' => 'field4', 'name' => 'field4']
			]
		];

		$this->formBuilderMock
			->expects($this->once())
			->method('prepareForm')
			->with($formData)
			->willReturn($preparedForm);

		$result = $this->collector->prepareForm($formData);

		static::assertSame($preparedForm, $result);
	}


	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreatePlaylistNameField(): void
	{
		$value = 'My Playlist';
		$expectedField = $this->createStub(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->with('playlist_name', 'playlists')
			->willReturn('Playlist name');

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::TEXT,
				'id' => 'playlist_name',
				'name' => 'playlist_name',
				'title' => 'Playlist name',
				'label' => 'Playlist name',
				'value' => $value,
				'rules' => ['required' => true, 'minlength' => 2],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$result = $this->collector->createPlaylistNameField($value);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateUIDField(): void
	{
		$value = '12345';
		$username = 'user1';
		$UID = 99;
		$expectedField = $this->createStub(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))
			->method('translate')
			->with('owner', 'main')
			->willReturn('Owner');

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::AUTOCOMPLETE,
				'id' => 'UID',
				'name' => 'UID',
				'title' => 'Owner',
				'label' => 'Owner',
				'value' => $value,
				'data-label' => $username,
				'default_value' => $UID,
			])
			->willReturn($expectedField);

		$result = $this->collector->createUIDField($value, $username, $UID);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateTimeLimitField(): void
	{
		$value = 120;
		$defaultValue = 60;
		$expectedField = $this->createStub(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['time_limit_explanation', 'playlists', [], 'Explanation for time limit'],
				['time_limit', 'playlists', [], 'Time Limit']
			]);

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::NUMBER,
				'id' => 'time_limit',
				'name' => 'time_limit',
				'title' => 'Explanation for time limit',
				'label' => 'Time Limit',
				'value' => $value,
				'min' => 0,
				'default_value' => $defaultValue
			])
			->willReturn($expectedField);

		$this->collector->createTimeLimitField($value, $defaultValue);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateHiddenPlaylistIdField(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$value = 101;
		$expectedField = $this->createStub(FieldInterface::class);

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::HIDDEN,
				'id' => 'playlist_id',
				'name' => 'playlist_id',
				'value' => $value,
			])
			->willReturn($expectedField);

		$result = $this->collector->createHiddenPlaylistIdField($value);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreatePlaylistModeField(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$value = '2';
		$expectedField = $this->createStub(FieldInterface::class);

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::HIDDEN,
				'id' => 'playlist_mode',
				'name' => 'playlist_mode',
				'value' => $value,
			])
			->willReturn($expectedField);

		$result = $this->collector->createPlaylistModeField($value);
		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateCSRFTokenField(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$expectedField = $this->createStub(FieldInterface::class);

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([
				'type' => FieldType::CSRF,
				'id' => BaseEditParameters::PARAMETER_CSRF_TOKEN,
				'name' => BaseEditParameters::PARAMETER_CSRF_TOKEN,
			])
			->willReturn($expectedField);

		$result = $this->collector->createCSRFTokenField();

		static::assertSame($expectedField, $result);
	}
}
