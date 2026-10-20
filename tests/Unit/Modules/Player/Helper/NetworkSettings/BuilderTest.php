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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\UrlField;
use App\Modules\Player\Helper\NetworkSettings\Builder;
use App\Modules\Player\Helper\NetworkSettings\FormElementsCreator;
use App\Modules\Player\Helper\NetworkSettings\Parameters;
use App\Modules\Player\Helper\NetworkSettings\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private Validator&MockObject $validatorMock;
	private Parameters&MockObject $parametersMock;
	private Builder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);
		$this->validatorMock = $this->createMock(Validator::class);
		$this->parametersMock = $this->createMock(Parameters::class);
		$this->builder = new Builder(
			$this->parametersMock,
			$this->validatorMock,
			$this->formElementsCreatorMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithEmptyData(): void
	{
		$networkData = ['player_id' => 0];
		$mockIsIntranetField = $this->createMock(FieldInterface::class);
		$mockApiEndpointField = $this->createMock(UrlField::class);
		$mockPlayerIdField = $this->createMock(FieldInterface::class);
		$mockCsrfTokenField = $this->createMock(FieldInterface::class);
		$preparedForm = [
			Parameters::PARAMETER_IS_INTRANET => $mockIsIntranetField,
			Parameters::PARAMETER_API_ENDPOINT => $mockApiEndpointField,
			'player_id' => $mockPlayerIdField,
			BaseEditParameters::PARAMETER_CSRF_TOKEN => $mockCsrfTokenField,
		];

		$this->formElementsCreatorMock->expects($this->once())->method('createIsIntranet')
			->with(false)
			->willReturn($mockIsIntranetField);

		$this->formElementsCreatorMock->expects($this->once())->method('createApiEndpointField')
			->with('')
			->willReturn($mockApiEndpointField);

		$this->formElementsCreatorMock->expects($this->once())->method('createHiddenPlayerIdField')
			->with(0)
			->willReturn($mockPlayerIdField);

		$this->formElementsCreatorMock->expects($this->once())->method('createCSRFTokenField')
			->willReturn($mockCsrfTokenField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('prepareForm')
			->with($preparedForm)
			->willReturn($preparedForm);

		$result = $this->builder->buildForm($networkData);

		static::assertSame($preparedForm, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testHandleUserInputWithValidData(): void
	{
		$postData = [
			'player_id' => 1,
			'player_name' => 'Test Player',
			'is_intranet' => 1,
			'api_endpoint' => 'https://example.com',
		];

		$expectedResult = ['status' => 'success'];

		$this->parametersMock->expects($this->once())->method('setUserInputs')
			->with($postData)
			->willReturnSelf();

		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$this->validatorMock->expects($this->once())->method('validateUserInput')
			->willReturn($expectedResult);

		$result = $this->builder->handleUserInput($postData);

		static::assertSame($expectedResult, $result);
	}
}
