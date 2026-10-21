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

namespace Tests\Unit\Modules\Playlists\Helper\Widgets;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Widgets\Orchestrator;
use App\Modules\Playlists\Helper\Widgets\ResponseBuilder;
use App\Modules\Playlists\Services\WidgetsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private BaseValidator&Stub $validatorStub;
	private WidgetsService&MockObject $widgetsServiceMock;
	private ResponseInterface&Stub $responseStub;
	private Orchestrator $orchestrator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->responseBuilderMock = $this->createMock(ResponseBuilder::class);
		$this->validatorStub       = $this->createStub(BaseValidator::class);
		$this->widgetsServiceMock  = $this->createMock(WidgetsService::class);
		$this->responseStub        = $this->createStub(ResponseInterface::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorStub,
			$this->widgetsServiceMock
		);
	}

	#[Group('units')]
	public function testValidateWithInvalidItemId(): void
	{
		$this->expectValidationError('invalidItemId');

		$this->orchestrator->setInput([])->validate($this->responseStub);
	}

	#[Group('units')]
	public function testValidateWithValidItemId(): void
	{
		$this->responseBuilderMock->expects($this->never())->method('invalidItemId');
		$this->widgetsServiceMock->expects($this->never())->method($this->anything());

		static::assertNull($this->orchestrator->setInput(['item_id' => '12'])->validate($this->responseStub));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateSaveWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['item_id' => 12, 'csrf_token' => 'wrong'])->validateSave($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateSaveWithInvalidItemId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidItemId');

		$this->orchestrator->setInput(['csrf_token' => 'token'])->validateSave($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetch(): void
	{
		$this->prepareValid(['item_id' => 12]);

		$data = ['item_id' => 12, 'item_name' => 'Clock'];
		$this->widgetsServiceMock->expects($this->once())->method('fetchWidgetByItemId')
			->with(12)
			->willReturn($data);
		$this->expectSuccess(['data' => $data]);

		$this->orchestrator->fetch($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetchWithLoadFailure(): void
	{
		$this->prepareValid(['item_id' => 12]);

		$this->widgetsServiceMock->expects($this->once())->method('fetchWidgetByItemId')
			->with(12)
			->willReturn([]);
		$this->expectError('widgetLoadFailed');

		$this->orchestrator->fetch($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSave(): void
	{
		$input = ['item_id' => 12, 'csrf_token' => 'token', 'timezone' => 'Europe/Berlin'];
		$this->prepareValid($input);

		$this->widgetsServiceMock->expects($this->once())->method('saveWidget')
			->with(12, $input)
			->willReturn(true);
		$this->expectSuccess([]);

		$this->orchestrator->save($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveWithFailure(): void
	{
		$input = ['item_id' => 12, 'csrf_token' => 'token'];
		$this->prepareValid($input);

		$this->widgetsServiceMock->expects($this->once())->method('saveWidget')
			->with(12, $input)
			->willReturn(false);
		$this->widgetsServiceMock->expects($this->once())->method('getErrorText')->willReturn('Save error');
		$this->responseBuilderMock->expects($this->never())->method('generalSuccess');
		$this->responseBuilderMock->expects($this->once())->method('saveFailed')
			->with($this->responseStub, 'Save error')
			->willReturn($this->responseStub);

		$this->orchestrator->save($this->responseStub);
	}

	/**
	 * Runs the validation, which has to pass, because it sets the item id the actions need.
	 *
	 * @param array<string,mixed> $input
	 */
	private function prepareValid(array $input): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);

		static::assertNull($this->orchestrator->setInput($input)->validateSave($this->responseStub));
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function expectSuccess(array $data): void
	{
		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseStub, $data)
			->willReturn($this->responseStub);
	}

	/**
	 * @param non-empty-string $method
	 */
	private function expectValidationError(string $method): void
	{
		$this->widgetsServiceMock->expects($this->never())->method($this->anything());
		$this->expectError($method);
	}

	/**
	 * @param non-empty-string $method
	 */
	private function expectError(string $method): void
	{
		$this->responseBuilderMock->expects($this->never())->method('generalSuccess');
		$this->responseBuilderMock->expects($this->once())->method($method)
			->with($this->responseStub)
			->willReturn($this->responseStub);
	}
}
