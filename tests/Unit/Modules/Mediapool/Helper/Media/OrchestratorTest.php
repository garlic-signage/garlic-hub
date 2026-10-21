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

namespace Tests\Unit\Modules\Mediapool\Helper\Media;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Helper\Media\Orchestrator;
use App\Modules\Mediapool\Helper\Media\ResponseBuilder;
use App\Modules\Mediapool\Services\MediaService;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\DataProvider;
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
	private MediaService&MockObject $mediaServiceMock;
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
		$this->mediaServiceMock    = $this->createMock(MediaService::class);
		$this->responseStub        = $this->createStub(ResponseInterface::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorStub,
			$this->mediaServiceMock
		);
	}

	#[Group('units')]
	public function testValidateListWithoutNode(): void
	{
		$this->expectValidationError('missingNode');

		$this->orchestrator->setInput(['filter' => ''])->validateList($this->responseStub);
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function filterProvider(): array
	{
		return [
			'images'    => ['images', 'image'],
			'videos'    => ['videos', 'video'],
			'audios'    => ['audios', 'audio'],
			'documents' => ['documents', 'application/pdf'],
			'unknown'   => ['unknown', ''],
			'empty'     => ['', ''],
		];
	}

	/**
	 * @throws DBALException
	 * @throws UserException
	 */
	#[Group('units')]
	#[DataProvider('filterProvider')]
	public function testListMapsFilter(string $input, string $filter): void
	{
		$this->prepareValid(['node_id' => '5', 'filter' => $input], 'validateList');
		$this->mediaServiceMock->expects($this->once())->method('listMedia')->with(5, $filter)->willReturn([['media_id' => 'a']]);
		$this->expectSuccess(['media_list' => [['media_id' => 'a']]]);

		$this->orchestrator->list($this->responseStub);
	}

	#[Group('units')]
	public function testValidateGetInfoWithoutMediaId(): void
	{
		$this->expectValidationError('missingMediaId');

		$this->orchestrator->setInput([])->validateGetInfo($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testGetInfo(): void
	{
		$this->prepareValid(['media_id' => 'abc'], 'validateGetInfo');
		$this->mediaServiceMock->expects($this->once())->method('fetchMedia')->with('abc')->willReturn(['filename' => 'x.jpg']);
		$this->expectSuccess(['media' => ['filename' => 'x.jpg']]);

		$this->orchestrator->getInfo($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateEditWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['media_id' => 'abc', 'filename' => 'f', 'description' => 'd'])->validateEdit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateEditWithoutMediaId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingMediaId');

		$this->orchestrator->setInput(['filename' => 'f', 'description' => 'd'])->validateEdit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateEditWithoutFilename(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingFilename');

		$this->orchestrator->setInput(['media_id' => 'abc', 'description' => 'd'])->validateEdit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateEditWithoutDescription(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingDescription');

		$this->orchestrator->setInput(['media_id' => 'abc', 'filename' => 'f'])->validateEdit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditAcceptsEmptyDescription(): void
	{
		$this->prepareValid(['media_id' => 'abc', 'filename' => 'new.jpg', 'description' => ''], 'validateEdit');
		$this->mediaServiceMock->expects($this->once())->method('updateMedia')->with('abc', 'new.jpg', '')->willReturn(1);
		$this->expectSuccess([]);

		$this->orchestrator->edit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithMediaIdWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['media_id' => 'abc'])->validateWithMediaId($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithMediaIdWithoutMediaId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingMediaId');

		$this->orchestrator->setInput([])->validateWithMediaId($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$this->prepareValid(['media_id' => 'abc'], 'validateWithMediaId');
		$this->mediaServiceMock->expects($this->once())->method('deleteMedia')->with('abc')->willReturn(1);
		$this->expectSuccess(['data' => ['deleted_media' => 1]]);

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testClone(): void
	{
		$this->prepareValid(['media_id' => 'abc'], 'validateWithMediaId');
		$this->mediaServiceMock->expects($this->once())->method('cloneMedia')->with('abc')->willReturn(['media_id' => 'def']);
		$this->expectSuccess(['new_media' => ['media_id' => 'def']]);

		$this->orchestrator->clone($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateMoveWithoutNode(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingMediaIdOrNode');

		$this->orchestrator->setInput(['media_id' => 'abc'])->validateMove($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testMove(): void
	{
		$this->prepareValid(['media_id' => 'abc', 'node_id' => '7'], 'validateMove');
		$this->mediaServiceMock->expects($this->once())->method('moveMedia')->with('abc', 7)->willReturn(1);
		$this->expectSuccess(['data' => ['deleted_media' => 1]]);

		$this->orchestrator->move($this->responseStub);
	}

	/**
	 * Runs the validation, which has to pass before an action is executed.
	 *
	 * @param array<string,mixed> $input
	 * @param non-empty-string $validateMethod
	 */
	private function prepareValid(array $input, string $validateMethod): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);

		static::assertNull($this->orchestrator->setInput($input)->$validateMethod($this->responseStub));
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
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$this->responseBuilderMock->expects($this->never())->method('generalSuccess');
		$this->responseBuilderMock->expects($this->once())->method($method)
			->with($this->responseStub)
			->willReturn($this->responseStub);
	}
}
