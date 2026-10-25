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

namespace Tests\Integration\Modules\Playlists\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Validation\BaseValidator;
use App\Modules\Playlists\Controller\ExportController;
use App\Modules\Playlists\Helper\Export\Orchestrator;
use App\Modules\Playlists\Helper\Export\ResponseBuilder;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Modules\Playlists\BuildsExportService;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The export of a playlist /async/playlists (PUT). The files go into a temporary directory instead of var/smil.
 */
#[Group('acl')]
class ExportControllerTest extends PlaylistsControllerTestCase
{
	use BuildsExportService;

	private MediaWorkspace $workspace;
	private ExportController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace  = new MediaWorkspace();
		$this->controller = new ExportController(new Orchestrator(
			new ResponseBuilder($this->container->get(JsonResponseHandler::class), $this->container->get(Translator::class)),
			$this->container->get(BaseValidator::class),
			$this->buildExportService($this->workspace)
		));
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testThePlaylistIsExported(): void
	{
		$this->actingAs(2);

		$response = $this->controller->export($this->request('PUT', body: $this->withToken(['playlist_id' => '1'])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertFileExists($this->workspace->absolute('/var/smil/playlists/1/items.smil'));
		static::assertSame(68, (int) $this->databaseValue('SELECT duration FROM playlists WHERE playlist_id = 1'));
	}

	public function testTheExportNeedsTheTokenAndAPlaylist(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->export($this->request('PUT', body: ['playlist_id' => '1']), $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->export($this->request('PUT', body: ['playlist_id' => '1', 'csrf_token' => 'wrong']), $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->export($this->request('PUT', body: $this->withToken([])), $this->newResponse()), 'Playlist ID not valid.');
		static::assertFileDoesNotExist($this->workspace->absolute('/var/smil/playlists/1/items.smil'));
	}

	public function testAForeignPlaylistIsNotExported(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->export($this->request('PUT', body: $this->withToken(['playlist_id' => '2'])), $this->newResponse());
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertFileDoesNotExist($this->workspace->absolute('/var/smil/playlists/2/items.smil'));
		}
	}

	public function testAnUnknownPlaylistIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->export($this->request('PUT', body: $this->withToken(['playlist_id' => '999'])), $this->newResponse());
	}

	public function testWithoutABodyOrWithATokenOfTheWrongTypeNothingIsDone(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->export($this->request('PUT')->withParsedBody(null), $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->export($this->request('PUT', body: ['csrf_token' => ['x'], 'item_id' => '1', 'playlist_id' => '1']), $this->newResponse()), 'CSRF token validation error.');
	}
}
