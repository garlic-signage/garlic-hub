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

namespace Tests\Unit\Modules\Player\Helper\NetworkSettings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\UrlField;
use App\Modules\Player\Helper\NetworkSettings\FormElementsCreator;
use App\Modules\Player\Helper\NetworkSettings\Parameters;
use App\Modules\Player\Helper\NetworkSettings\SettingsFormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsFormBuilderTest extends TestCase
{
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private SettingsFormBuilder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);
		$this->builder = new SettingsFormBuilder($this->formElementsCreatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithDefaults(): void
	{
		$this->expectFields(false, '', 0);

		$this->builder->buildForm(['player_id' => 0]);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithPlayerData(): void
	{
		$this->expectFields(true, 'https://example.com', 12);

		$this->builder->buildForm([
			'player_id' => '12',
			Parameters::PARAMETER_IS_INTRANET => 1,
			Parameters::PARAMETER_API_ENDPOINT => 'https://example.com'
		]);
	}

	/**
	 * @throws Exception
	 */
	private function expectFields(bool $isIntranet, string $apiEndpoint, int $playerId): void
	{
		$isIntranetField  = $this->createStub(FieldInterface::class);
		$apiEndpointField = $this->createStub(UrlField::class);
		$playerIdField    = $this->createStub(FieldInterface::class);
		$csrfTokenField   = $this->createStub(FieldInterface::class);
		$form = [
			Parameters::PARAMETER_IS_INTRANET        => $isIntranetField,
			Parameters::PARAMETER_API_ENDPOINT       => $apiEndpointField,
			'player_id'                              => $playerIdField,
			BaseEditParameters::PARAMETER_CSRF_TOKEN => $csrfTokenField,
		];

		$this->formElementsCreatorMock->expects($this->once())->method('createIsIntranet')
			->with($isIntranet)->willReturn($isIntranetField);
		$this->formElementsCreatorMock->expects($this->once())->method('createApiEndpointField')
			->with($apiEndpoint)->willReturn($apiEndpointField);
		$this->formElementsCreatorMock->expects($this->once())->method('createHiddenPlayerIdField')
			->with($playerId)->willReturn($playerIdField);
		$this->formElementsCreatorMock->expects($this->once())->method('createCSRFTokenField')
			->willReturn($csrfTokenField);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with($form)->willReturn(['hidden' => [], 'visible' => []]);
	}
}
