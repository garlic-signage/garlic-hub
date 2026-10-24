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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

class BaseResponseBuilderTest extends TestCase
{
	private Translator&Stub $translatorStub;
	private BaseResponseBuilder $baseResponseBuilder;

	protected function setUp(): void
	{
		parent::setUp();

		$this->translatorStub     = $this->createStub(Translator::class);
		$this->baseResponseBuilder = new class(new JsonResponseHandler(), $this->translatorStub) extends BaseResponseBuilder {};
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCsrfTokenMismatchAnswersWithTranslatedError(): void
	{
		// only the right translation key gives a text
		$this->translatorStub->method('translate')->willReturnMap([['csrf_token_mismatch', 'security', [], 'CSRF Token Mismatch']]);

		$response = $this->baseResponseBuilder->csrfTokenMismatch(new Response());

		static::assertSame(400, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => 'CSRF Token Mismatch'], $this->body($response));
	}

	#[Group('units')]
	public function testGeneralErrorAnswersWithTheGivenMessage(): void
	{
		$response = $this->baseResponseBuilder->generalError(new Response(), 'An unexpected error occurred');

		static::assertSame(400, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => 'An unexpected error occurred'], $this->body($response));
	}

	#[Group('units')]
	public function testGeneralSuccessAnswersWithSuccessAndTheGivenData(): void
	{
		$response = $this->baseResponseBuilder->generalSuccess(new Response(), ['message' => 'Operation was successful']);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(['success' => true, 'message' => 'Operation was successful'], $this->body($response));
	}

	/**
	 * @return array<mixed>
	 */
	private function body(ResponseInterface $response): array
	{
		$response->getBody()->rewind();

		return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
	}
}
