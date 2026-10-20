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


namespace Tests\Unit\Framework\Controller;

use App\Framework\Controller\BaseResponseBuilder;
use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ConcreteResponseBuilder extends BaseResponseBuilder{}
class BaseResponseBuilderTest extends TestCase
{
	private JsonResponseHandler&MockObject $jsonResponseHandlerMock;
	private Translator&MockObject $translatorMock;
	private ConcreteResponseBuilder $baseResponseBuilder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->jsonResponseHandlerMock = $this->createMock(JsonResponseHandler::class);
		$this->translatorMock          = $this->createMock(Translator::class);
		$this->baseResponseBuilder      = new ConcreteResponseBuilder($this->jsonResponseHandlerMock, $this->translatorMock);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCsrfTokenMismatch(): void
	{
		$mockResponse = $this->createMock(ResponseInterface::class);
		$translatedError = 'CSRF Token Mismatch';

		$this->translatorMock->expects($this->once())->method('translate')
			->with('csrf_token_mismatch', 'security')
			->willReturn($translatedError);

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($mockResponse, $translatedError)
			->willReturn($mockResponse);

		$response = $this->baseResponseBuilder->csrfTokenMismatch($mockResponse);

		static::assertSame($mockResponse, $response);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGeneralErrorReturnsJsonErrorWithMessage(): void
	{
		$mockResponse = $this->createMock(ResponseInterface::class);
		$errorMessage = 'An unexpected error occurred';

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($mockResponse, $errorMessage)
			->willReturn($mockResponse);

		$response = $this->baseResponseBuilder->generalError($mockResponse, $errorMessage);

		static::assertSame($mockResponse, $response);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGeneralSuccessReturnsJsonSuccessWithMessage(): void
	{
		$mockResponse = $this->createMock(ResponseInterface::class);
		$successMessage = ['message' => 'Operation was successful'];

		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonSuccess')
			->with($mockResponse, $successMessage)
			->willReturn($mockResponse);

		$response = $this->baseResponseBuilder->generalSuccess($mockResponse, $successMessage);

		static::assertSame($mockResponse, $response);
	}
}
