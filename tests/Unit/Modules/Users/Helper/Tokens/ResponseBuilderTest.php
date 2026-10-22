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

namespace Tests\Unit\Modules\Users\Helper\Tokens;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Users\Helper\Tokens\ResponseBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ResponseBuilderTest extends TestCase
{
	private JsonResponseHandler&MockObject $jsonResponseHandlerMock;
	private ResponseInterface&Stub $responseStub;
	private ResponseBuilder $responseBuilder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->jsonResponseHandlerMock = $this->createMock(JsonResponseHandler::class);
		$this->responseStub            = $this->createStub(ResponseInterface::class);

		$this->responseBuilder = new ResponseBuilder(
			$this->jsonResponseHandlerMock,
			$this->createStub(Translator::class)
		);
	}

	#[Group('units')]
	public function testNoRightsIsForbidden(): void
	{
		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseStub, 'No rights to handle tokens.', 403)
			->willReturn($this->responseStub);

		static::assertSame($this->responseStub, $this->responseBuilder->noRights($this->responseStub));
	}

	#[Group('units')]
	public function testMissingUID(): void
	{
		$this->expectJsonError('UID not transmitted.');

		static::assertSame($this->responseStub, $this->responseBuilder->missingUID($this->responseStub));
	}

	#[Group('units')]
	public function testMissingPurpose(): void
	{
		$this->expectJsonError('Purpose not transmitted.');

		static::assertSame($this->responseStub, $this->responseBuilder->missingPurpose($this->responseStub));
	}

	#[Group('units')]
	public function testTokenNotEditable(): void
	{
		$this->expectJsonError('Token not editable.');

		static::assertSame($this->responseStub, $this->responseBuilder->tokenNotEditable($this->responseStub));
	}

	private function expectJsonError(string $message): void
	{
		$this->jsonResponseHandlerMock->expects($this->once())->method('jsonError')
			->with($this->responseStub, $message)
			->willReturn($this->responseStub);
	}
}
