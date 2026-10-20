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

namespace Tests\Unit\Modules\Mediapool\Helper\Uploads;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Helper\Uploads\Orchestrator;
use App\Modules\Mediapool\Helper\Uploads\ResponseBuilder;
use App\Modules\Mediapool\Services\UploadService;
use Doctrine\DBAL\Exception as DBALException;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\UploadedFile;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private BaseValidator&Stub $validatorStub;
	private UploadService&MockObject $uploadServiceMock;
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
		$this->uploadServiceMock   = $this->createMock(UploadService::class);
		$this->responseStub        = $this->createStub(ResponseInterface::class);

		$userSessionStub = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(1);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$userSessionStub,
			$this->validatorStub,
			$this->uploadServiceMock
		);
	}

	#[Group('units')]
	public function testValidateSearchStockImagesWithForeignHost(): void
	{
		$this->expectValidationError('hostNotAllowed');

		$this->orchestrator->setInput(['api_url' => 'https://evil.example.com/api'])->validateSearchStockImages($this->responseStub);
	}

	#[Group('units')]
	public function testValidateSearchStockImagesWithoutUrl(): void
	{
		$this->expectValidationError('hostNotAllowed');

		$this->orchestrator->setInput([])->validateSearchStockImages($this->responseStub);
	}

	/**
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testSearchStockImages(): void
	{
		$input = ['api_url' => 'https://api.pexels.com/v1/search?query=cat', 'headers' => ['Authorization' => 'key']];
		static::assertNull($this->orchestrator->setInput($input)->validateSearchStockImages($this->responseStub));

		$this->uploadServiceMock->expects($this->once())->method('requestApi')
			->with($input['api_url'], ['Authorization' => 'key'])
			->willReturn(['photos' => []]);
		$this->expectSuccess(['data' => ['photos' => []]]);

		$this->orchestrator->searchStockImages($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUploadLocalFileWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['node_id' => 1])->setUploadedFiles(['file' => 'x'])->validateUploadLocalFile($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUploadLocalFileWithoutUploads(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('noFilesToUpload');

		$this->orchestrator->setInput(['node_id' => 1])->setUploadedFiles([])->validateUploadLocalFile($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUploadLocalFileWithoutNode(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingNode');

		$this->orchestrator->setInput([])->setUploadedFiles(['file' => 'x'])->validateUploadLocalFile($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUploadLocalFileWithoutFileField(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('noFiles');

		$this->orchestrator->setInput(['node_id' => 1])->setUploadedFiles(['other' => 'x'])->validateUploadLocalFile($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadLocalFile(): void
	{
		$file = $this->createStub(UploadedFile::class);
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		static::assertNull($this->orchestrator
			->setInput(['node_id' => '5', 'metadata' => '{"origin":"webcam"}'])
			->setUploadedFiles(['file' => $file])
			->validateUploadLocalFile($this->responseStub));

		$result = ['success' => true, 'message' => 'x.jpg successful uploaded'];
		$this->uploadServiceMock->expects($this->once())->method('uploadMediaFiles')
			->with(5, 1, $file, ['origin' => 'webcam'])
			->willReturn([$result]);
		$this->responseBuilderMock->expects($this->once())->method('uploadResult')
			->with($this->responseStub, $result)
			->willReturn($this->responseStub);

		$this->orchestrator->uploadLocalFile($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUploadFromUrlWithoutNode(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingNode');

		$this->orchestrator->setInput(['external_link' => 'https://x'])->validateUploadFromUrl($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUploadFromUrlWithoutLink(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingExternalLink');

		$this->orchestrator->setInput(['node_id' => 1])->validateUploadFromUrl($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadFromUrlWithInvalidMetadata(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		static::assertNull($this->orchestrator
			->setInput(['node_id' => 2, 'external_link' => 'https://cdn.example.com/a.jpg', 'metadata' => 'no json'])
			->validateUploadFromUrl($this->responseStub));

		$result = ['success' => false, 'error_message' => 'Download failed'];
		$this->uploadServiceMock->expects($this->once())->method('uploadExternalMedia')
			->with(2, 1, 'https://cdn.example.com/a.jpg', [])
			->willReturn($result);
		$this->responseBuilderMock->expects($this->once())->method('uploadResult')
			->with($this->responseStub, $result)
			->willReturn($this->responseStub);

		$this->orchestrator->uploadFromUrl($this->responseStub);
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
		$this->uploadServiceMock->expects($this->never())->method('uploadMediaFiles');
		$this->uploadServiceMock->expects($this->never())->method('uploadExternalMedia');
		$this->uploadServiceMock->expects($this->never())->method('requestApi');
		$this->responseBuilderMock->expects($this->never())->method('generalSuccess');
		$this->responseBuilderMock->expects($this->once())->method($method)
			->with($this->responseStub)
			->willReturn($this->responseStub);
	}
}
