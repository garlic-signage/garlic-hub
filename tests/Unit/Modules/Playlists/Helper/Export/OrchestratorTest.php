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

namespace Tests\Unit\Modules\Playlists\Helper\Export;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Export\Orchestrator;
use App\Modules\Playlists\Helper\Export\ResponseBuilder;
use App\Modules\Playlists\Services\ExportService;
use Doctrine\DBAL\Exception as DBALException;
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
	private ExportService&MockObject $exportServiceMock;
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
		$this->exportServiceMock   = $this->createMock(ExportService::class);
		$this->responseStub        = $this->createStub(ResponseInterface::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorStub,
			$this->exportServiceMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['playlist_id' => 1, 'csrf_token' => 'wrong'])->validate($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithInvalidPlaylistId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput(['csrf_token' => 'token'])->validate($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testExport(): void
	{
		$this->prepareValid(['playlist_id' => '1', 'csrf_token' => 'token']);

		$this->exportServiceMock->expects($this->once())->method('exportToSmil')->with(1)->willReturn(1);
		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseStub, [])
			->willReturn($this->responseStub);

		$this->orchestrator->export($this->responseStub);
	}

	/**
	 * Runs the validation, which has to pass, because it sets the playlist id the export needs.
	 *
	 * @param array<string,mixed> $input
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function prepareValid(array $input): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);

		static::assertNull($this->orchestrator->setInput($input)->validate($this->responseStub));
	}

	/**
	 * @param non-empty-string $method
	 */
	private function expectValidationError(string $method): void
	{
		$this->exportServiceMock->expects($this->never())->method($this->anything());
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
