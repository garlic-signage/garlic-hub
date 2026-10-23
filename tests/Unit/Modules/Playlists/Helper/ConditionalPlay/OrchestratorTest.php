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


namespace Tests\Unit\Modules\Playlists\Helper\ConditionalPlay;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Modules\Playlists\Helper\ConditionalPlay\Orchestrator;
use App\Modules\Playlists\Helper\ConditionalPlay\Schema;
use App\Modules\Playlists\Helper\ConditionalPlay\ResponseBuilder;
use App\Modules\Playlists\Helper\ConditionalPlay\TemplatePreparer;
use App\Modules\Playlists\Services\ConditionalPlayService;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private BaseValidator&MockObject $validatorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private ResponseInterface&Stub $responseStub;
	private ConditionalPlayService&MockObject $conditionalPlayServiceMock;
	private Schema $schema;
	private Orchestrator $orchestrator;

	/**
	 * @throws Exception
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->responseBuilderMock = $this->createMock(ResponseBuilder::class);
		$this->validatorMock = $this->createMock(BaseValidator::class);
		$this->templatePreparerMock = $this->createMock(TemplatePreparer::class);
		$this->conditionalPlayServiceMock = $this->createMock(ConditionalPlayService::class);

		$this->responseStub = $this->createStub(ResponseInterface::class);

		$this->schema = new Schema();

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorMock,
			$this->templatePreparerMock,
			$this->conditionalPlayServiceMock,
			$this->schema
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenCsrfMismatch(): void
	{
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->conditionalPlayServiceMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('invalid_token')
			->willReturn(false);

		$this->responseBuilderMock->expects($this->once())->method('csrfTokenMismatch')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([BaseEditParameters::PARAMETER_CSRF_TOKEN => 'invalid_token']);

		$this->orchestrator->validateSave($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException

	 */
	#[Group('units')]
	public function testValidateWithTokenFailsNoItemId(): void
	{
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->conditionalPlayServiceMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_token')
			->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('invalidItemId')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_token']);

		$this->orchestrator->validateSave($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenCallsValidate(): void
	{
		$this->expectUntouched($this->responseBuilderMock, $this->templatePreparerMock, $this->conditionalPlayServiceMock);
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_token')
			->willReturn(true);

		$this->orchestrator->setInput(['item_id' => '1', BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_token']);

		$this->orchestrator->validateSave($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateSaveRejectsInvalidConditionalData(): void
	{
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->conditionalPlayServiceMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')->with('valid_token')->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('invalidConditionalData')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([
			'item_id'    => '1',
			'csrf_token' => 'valid_token',
			'date'       => ['from' => "2026-01-01') or ('1'='1", 'until' => '']
		]);

		static::assertSame($this->responseStub, $this->orchestrator->validateSave($this->responseStub));
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFetchSuccess(): void
	{
		$this->validatorMock->expects($this->never())->method($this->anything());
		$itemData = ['item_id' => 123, 'conditional' => ['key' => 'value']];

		$this->conditionalPlayServiceMock->expects($this->once())->method('fetchConditionalByItemId')->with(123)->willReturn($itemData);
		$this->templatePreparerMock->expects($this->once())->method('prepare')->with(123, $itemData['conditional']);
		$this->templatePreparerMock->expects($this->once())->method('render')->willReturn('<html lang="en"></html>');
		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')->with($this->responseStub, ['data' => $itemData, 'html' => '<html lang="en"></html>'])->willReturn($this->responseStub);

		$this->initMocks();
		$this->orchestrator->fetch($this->responseStub);
	}

	/**
	 * @throws ModuleException
	 * @throws UserException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSaveSuccess(): void
	{
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')->with('valid_token')->willReturn(true);
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->conditionalPlayServiceMock->expects($this->once())->method('saveConditionalPlay')
			->with(123, [
				'date'     => ['from' => '2026-01-31', 'until' => ''],
				'time'     => ['from' => '', 'until' => ''],
				'weekdays' => [2 => ['from' => 4, 'until' => 40]]
			]);

		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseStub, [])
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([
			'item_id'    => '123',
			'csrf_token' => 'valid_token',
			'unknown'    => 'dropped',
			'date'       => ['from' => '2026-01-31', 'until' => ''],
			'weekdays'   => ['2' => ['from' => 4, 'until' => 40]]
		]);
		static::assertNull($this->orchestrator->validateSave($this->responseStub));
		$this->orchestrator->save($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function initMocks():void
	{
		$this->orchestrator->setInput(['item_id' => '123', 'key' => 'value', BaseEditParameters::PARAMETER_CSRF_TOKEN => 'invalid_token']);
		$this->orchestrator->validate($this->responseStub);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
