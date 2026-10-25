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

namespace Tests\Unit\Modules\Player;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Modules\Player\Controller\PlayerController;
use App\Modules\Player\Helper\Datatable\PlayerFilterDefinition;
use App\Modules\Player\Services\PlayerService;
use Defuse\Crypto\Key;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\MemorySession;

/**
 * Two answers which the database of the tests (SQLite) does not give. Everything else of the player endpoints is
 * tested with the real database in tests/Integration/Modules/Player.
 */
class PlayerEdgeCasesTest extends TestCase
{
	/**
	 * The service found the player and could delete it, but the delete changed no row (somebody was faster).
	 */
	#[Group('units')]
	public function testAPlayerWhichWasDeletedMeanwhileIsReportedAsNotDeleted(): void
	{
		$service = $this->createStub(PlayerService::class);
		$service->method('delete')->willReturn(0);
		$csrf = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());
		$controller = new PlayerController(new JsonResponseHandler(), $service, $csrf);
		$request = new ServerRequestFactory()->createServerRequest('DELETE', '/async/player')->withParsedBody(['csrf_token' => $csrf->getToken(), 'player_id' => '3']);

		$response = $controller->delete($request, new Response());

		static::assertSame(400, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => 'Player not deleted.'], json_decode((string) $response->getBody(), true));
	}

	/**
	 * MariaDB has other functions for time than SQLite: the filter by activity is made for both.
	 */
	#[Group('units')]
	public function testTheFilterByActivityOfMariaDbCountsBackFromNow(): void
	{
		$definition = new PlayerFilterDefinition(new FilterConditions(), new MariaDBPlatform());

		$active   = $definition->whereForFiltering(['activity' => ['value' => 'active']]);
		$inactive = $definition->whereForFiltering(['activity' => ['value' => 'inactive']]);

		static::assertSame(['expression' => 'player.last_access >= DATE_SUB(NOW(), INTERVAL (2 * player.refresh) SECOND)'], $active['player.last_access']);
		static::assertSame(['expression' => 'player.last_access < DATE_SUB(NOW(), INTERVAL (2 * player.refresh) SECOND)'], $inactive['player.last_access']);
	}
}
