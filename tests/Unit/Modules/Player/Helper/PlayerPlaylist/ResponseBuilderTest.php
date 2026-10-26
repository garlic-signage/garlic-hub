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

namespace Tests\Unit\Modules\Player\Helper\PlayerPlaylist;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Player\Helper\PlayerPlaylist\ResponseBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

/**
 * The answers are what the browser reads: JSON body and status of the real JsonResponseHandler. The texts come
 * from the translator, which answers "module.key".
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
			'invalid player'       => ['invalidPlayerId', 'player.invalid_player_id'],
			'invalid playlist'     => ['invalidPlaylistId', 'player.invalid_playlist_id'],
			'player not reachable' => ['playerNotReachable', 'player.player_not_reachable'],
			'no playlist'          => ['noPlaylistAssigned', 'player.no_playlist_assigned'],
		];
	}

	#[Group('units')]
	#[DataProvider('errors')]
	public function testEveryErrorIsAJsonErrorWithTheTextOfTheLanguage(string $method, string $message): void
	{
		$response = $this->responseBuilder->$method(new Response());

		static::assertSame(400, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => $message], json_decode((string) $response->getBody(), true));
	}

	#[Group('units')]
	public function testTheGeneralAnswers(): void
	{
		static::assertSame(['success' => true, 'message' => 'ok'], json_decode((string) $this->responseBuilder->generalSuccess(new Response(), ['message' => 'ok'])->getBody(), true));
		static::assertSame(['success' => false, 'error_message' => 'bad'], json_decode((string) $this->responseBuilder->generalError(new Response(), 'bad')->getBody(), true));
	}
}
