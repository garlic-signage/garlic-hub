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

namespace Tests\Unit\Modules\Mediapool\Helper\Nodes;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Mediapool\Helper\Nodes\ResponseBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

/**
 * The answers are what the browser reads: the JSON body and the status, made by the real JsonResponseHandler.
 */
class ResponseBuilderTest extends TestCase
{
	private ResponseBuilder $responseBuilder;

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$this->responseBuilder = new ResponseBuilder(new JsonResponseHandler(), $translator);
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function errors(): array
	{
		return [
			'missingName' => ['missingName', 'node name is missing'],
			'missingNameOrId' => ['missingNameOrId', 'node name or id is missing'],
			'missingNodeId' => ['missingNodeId', 'NodeId is missing'],
			'missingMoveParameters' => ['missingMoveParameters', 'Source node, target node, or target region is missing']
		];
	}

	#[Group('units')]
	#[DataProvider('errors')]
	public function testEveryErrorIsAJsonErrorWithItsMessage(string $method, string $message): void
	{
		$response = $this->responseBuilder->$method(new Response());

		static::assertSame(400, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => $message], json_decode((string) $response->getBody(), true));
	}

	#[Group('units')]
	public function testTheTokenErrorHasTheTextOfTheLanguage(): void
	{
		$response = $this->responseBuilder->csrfTokenMismatch(new Response());

		static::assertSame(['success' => false, 'error_message' => 'security.csrf_token_mismatch'], json_decode((string) $response->getBody(), true));
	}

	#[Group('units')]
	public function testAGeneralSuccessAndAGeneralErrorAreMarked(): void
	{
		$success = $this->responseBuilder->generalSuccess(new Response(), ['data' => ['id' => 5]]);
		$error   = $this->responseBuilder->generalError(new Response(), 'It failed');

		static::assertSame(200, $success->getStatusCode());
		static::assertSame(['success' => true, 'data' => ['id' => 5]], json_decode((string) $success->getBody(), true));
		static::assertSame(['success' => false, 'error_message' => 'It failed'], json_decode((string) $error->getBody(), true));
	}

	#[Group('units')]
	public function testTheListOfTheTreeIsTheListItselfWithoutAnEnvelope(): void
	{
		$nodes    = [['title' => 'root', 'key' => 1]];
		$response = $this->responseBuilder->nodeList(new Response(), $nodes);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame($nodes, json_decode((string) $response->getBody(), true));
	}
}
