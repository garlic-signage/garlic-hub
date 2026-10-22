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


namespace Tests\Unit\Modules\Player\Helper\NetworkSettings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\UrlField;
use App\Modules\Player\Helper\NetworkSettings\FormElementsCreator;
use App\Modules\Player\Helper\NetworkSettings\Parameters;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FormElementsCreatorTest extends TestCase
{
	private FormElementsCreator $formElementsCreator;
	private FormBuilder&MockObject $formBuilderMock;
	private Translator&MockObject $translatorMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formBuilderMock = $this->createMock(FormBuilder::class);
		$this->translatorMock = $this->createMock(Translator::class);
		$this->formElementsCreator = new FormElementsCreator($this->formBuilderMock, $this->translatorMock);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateApiEndpointField(): void
	{
		$expectedFieldMock = $this->createMock(UrlField::class);
		$value = 'https://example.com/api';
		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::URL,
				'id' => Parameters::PARAMETER_API_ENDPOINT,
				'name' => Parameters::PARAMETER_API_ENDPOINT,
				'title' => 'PARAMETER_API_ENDPOINT_TITLE',
				'label' => 'PARAMETER_API_ENDPOINT_TITLE',
				'value' => $value
			])
			->willReturn($expectedFieldMock);

		$this->translatorMock->expects($this->once())->method('translate')
			->with(Parameters::PARAMETER_API_ENDPOINT, 'player')
			->willReturn('PARAMETER_API_ENDPOINT_TITLE');

		$expectedFieldMock->expects($this->once())->method('setPlaceholder')
			->with('http://localhost:8080/v2');
		$expectedFieldMock->expects($this->once())->method('setPattern')
			->with('https?://.*');

		$this->formElementsCreator->createApiEndpointField($value);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateIsIntranet(): void
	{
		$expectedFieldMock = $this->createMock(CheckboxField::class);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::CHECKBOX,
				'id' => Parameters::PARAMETER_IS_INTRANET,
				'name' => Parameters::PARAMETER_IS_INTRANET,
				'title' => 'PARAMETER_IS_INTRANET_TITLE',
			])
			->willReturn($expectedFieldMock);

		$this->translatorMock->expects($this->once())->method('translate')
			->with(Parameters::PARAMETER_IS_INTRANET, 'player')
			->willReturn('PARAMETER_IS_INTRANET_TITLE');

		$expectedFieldMock->expects($this->once())->method('setChecked')
			->with(false);

		$this->formElementsCreator->createIsIntranet(false);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateHiddenPlayerIdField(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$expectedFieldMock = $this->createStub(FieldInterface::class);
		$value = 123;

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::HIDDEN,
				'id' => 'player_id',
				'name' => 'player_id',
				'value' => $value,
			])
			->willReturn($expectedFieldMock);

		$result = $this->formElementsCreator->createHiddenPlayerIdField($value);

		static::assertSame($expectedFieldMock, $result);
	}


}