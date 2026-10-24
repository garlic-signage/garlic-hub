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

use App\Framework\Controller\JsonResponseHandler;
use JsonException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

class JsonResponseHandlerTest extends TestCase
{
	private JsonResponseHandler $jsonResponseHandler;

	protected function setUp(): void
	{
		parent::setUp();

		$this->jsonResponseHandler = new JsonResponseHandler();
	}

	#[Group('units')]
	public function testJsonResponseWritesDataAsJsonWithDefaultStatus(): void
	{
		$response = $this->jsonResponseHandler->jsonResponse(new Response(), ['anotherKey' => 'anotherValue']);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['anotherKey' => 'anotherValue'], $this->body($response));
	}

	#[Group('units')]
	public function testJsonResponseUsesGivenStatus(): void
	{
		$response = $this->jsonResponseHandler->jsonResponse(new Response(), ['a' => 1], 201);

		static::assertSame(201, $response->getStatusCode());
	}

	#[Group('units')]
	public function testJsonResponseKeepsSpecialCharacters(): void
	{
		$data     = ['text' => 'Grüße "quoted" <b>&</b> / 日本'];
		$response = $this->jsonResponseHandler->jsonResponse(new Response(), $data);

		static::assertSame($data, $this->body($response));
	}

	#[Group('units')]
	public function testJsonResponseSubstitutesInvalidUtf8(): void
	{
		$response = $this->jsonResponseHandler->jsonResponse(new Response(), ['name' => "broken\xB1name"]);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(['name' => "broken\u{FFFD}name"], $this->body($response));
	}

	#[Group('units')]
	public function testJsonResponseThrowsForDataWhichCanNotBeEncoded(): void
	{
		$this->expectException(JsonException::class);

		$this->jsonResponseHandler->jsonResponse(new Response(), ['value' => NAN]);
	}

	#[Group('units')]
	public function testJsonSuccessWithoutAdditionalData(): void
	{
		$response = $this->jsonResponseHandler->jsonSuccess(new Response());

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => true], $this->body($response));
	}

	#[Group('units')]
	public function testJsonSuccessWithAdditionalData(): void
	{
		$response = $this->jsonResponseHandler->jsonSuccess(new Response(), ['id' => 7, 'name' => 'Lobby']);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(['success' => true, 'id' => 7, 'name' => 'Lobby'], $this->body($response));
	}

	#[Group('units')]
	public function testJsonSuccessCanNotBeOverwrittenByData(): void
	{
		$response = $this->jsonResponseHandler->jsonSuccess(new Response(), ['success' => false]);

		static::assertSame(['success' => true], $this->body($response));
	}

	#[Group('units')]
	public function testJsonErrorWithDefaultStatus(): void
	{
		$response = $this->jsonResponseHandler->jsonError(new Response(), 'An error occurred');

		static::assertSame(400, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => 'An error occurred'], $this->body($response));
	}

	#[Group('units')]
	public function testJsonErrorWithCustomStatus(): void
	{
		$response = $this->jsonResponseHandler->jsonError(new Response(), 'Custom error', 500);

		static::assertSame(500, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => 'Custom error'], $this->body($response));
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
