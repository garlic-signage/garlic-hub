<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Blackbox\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * Which kind of answer an error gets: the JSON routes (/api, /async) answer with the JSON the frontend shows,
 * everything else with the error page of the web UI. Internals of an error never reach the visitor.
 */
#[Group('blackbox')]
class ErrorResponsesTest extends BlackboxTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function jsonPrefixes(): array
	{
		return ['async' => ['/async'], 'api' => ['/api']];
	}

	/**
	 * @return array<mixed>
	 */
	private function json(ResponseInterface $response): array
	{
		$data = json_decode((string) $response->getBody(), true);
		static::assertIsArray($data, 'The answer is no JSON: ' . substr((string) $response->getBody(), 0, 200));

		return $data;
	}

	#[DataProvider('jsonPrefixes')]
	public function testAnUnknownRouteOfTheJsonPartAnswersWithJson(string $prefix): void
	{
		$response = $this->browser()->get($prefix . '/does-not-exist');

		static::assertSame(404, $response->getStatusCode());
		static::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => 'Route not found'], $this->json($response));
	}

	public function testAnUnknownRouteForALoggedInUserAnswersWithJsonToo(): void
	{
		$browser = $this->browser();
		$browser->login('anna', 'Test1234!');

		$response = $browser->get('/async/does-not-exist');

		static::assertSame(404, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => 'Route not found'], $this->json($response));
	}

	public function testAWrongMethodOnAJsonRouteAnswersWith405AndJson(): void
	{
		$browser = $this->browser();
		$browser->login('anna', 'Test1234!');

		// /async/mediapool/node/{id} is a GET route
		$response = $browser->request('PATCH', '/async/mediapool/node/0');

		static::assertSame(405, $response->getStatusCode());
		static::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => 'Method not allowed'], $this->json($response));
	}

	public function testAnUnknownPageOfTheWebUiIsNoJson(): void
	{
		$response = $this->browser()->get('/does-not-exist');

		static::assertSame(404, $response->getStatusCode());
		static::assertStringNotContainsString('json', $response->getHeaderLine('Content-Type'));
		static::assertNull(json_decode((string) $response->getBody(), true), 'The web UI must not answer with the JSON of the frontend.');
	}

	public function testThePathOfTheErrorPageIsNotTakenFromTheJsonPart(): void
	{
		// a path which only contains the prefix somewhere else belongs to the web UI
		$response = $this->browser()->get('/x/async/does-not-exist');

		static::assertSame(404, $response->getStatusCode());
		static::assertNull(json_decode((string) $response->getBody(), true));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function jsonPaths(): array
	{
		return ['async itself' => ['/async'], 'api itself' => ['/api'], 'below async' => ['/async/x/y']];
	}

	#[DataProvider('jsonPaths')]
	public function testThePathsOfTheJsonPartAnswerWithJson(string $path): void
	{
		$response = $this->browser()->get($path);

		static::assertSame(404, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => 'Route not found'], $this->json($response));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function webPaths(): array
	{
		return ['starts like async' => ['/asyncfoo'], 'starts like api' => ['/apiary'], 'below such a path' => ['/asyncfoo/x'], 'async in the middle' => ['/x/api/y']];
	}

	/**
	 * A path which only begins with the letters of /async or /api belongs to the web UI.
	 */
	#[DataProvider('webPaths')]
	public function testAPathWhichOnlyStartsLikeTheJsonPartIsNoJson(string $path): void
	{
		$response = $this->browser()->get($path);

		static::assertSame(404, $response->getStatusCode());
		static::assertNull(json_decode((string) $response->getBody(), true), 'The web UI must not answer with the JSON of the frontend.');
	}

	/**
	 * A request may send an array or nothing where the page expects a text or a number (?name[]=x).
	 * That is a request which does not make sense, but no reason for an error page.
	 */
	public function testAManipulatedQueryOfADatatableIsNoServerError(): void
	{
		$browser = $this->browser();
		$browser->login('anna', 'Test1234!');

		$response = $browser->get('/player?player_name[]=x&elements_page[]=2&elements_per_page[]=5&sort_column[]=a&sort_order[]=desc&model[]=1');

		static::assertSame(200, $response->getStatusCode());
		static::assertStringContainsString('<form', (string) $response->getBody());
	}
}
