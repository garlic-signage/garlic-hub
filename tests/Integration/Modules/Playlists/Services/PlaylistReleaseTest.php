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

namespace Tests\Integration\Modules\Playlists\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Dashboard\PlaylistsDashboard;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\ExportService;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Playlists\Services\PlaylistRelease;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Modules\Playlists\BuildsExportService;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The release of playlists: while it is on, the owner asks for it and only the admin exports.
 * Fixtures: 1 = admin, playlist 1 of anna (2) with the items 1 to 5, playlist 3 of anna is empty.
 */
#[Group('acl')]
class PlaylistReleaseTest extends ContainerTestCase
{
	use BuildsExportService;

	private MediaWorkspace $workspace;
	private PlaylistRelease $release;
	private ExportService $exportService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->workspace     = new MediaWorkspace();
		$this->release       = $this->buildRelease(true);
		$this->exportService = $this->buildExportService($this->workspace, $this->release);
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testTheReleaseIsOffByDefault(): void
	{
		static::assertFalse($this->container->get(PlaylistRelease::class)->isEnabled());
	}

	public function testTheOwnerAsksForTheRelease(): void
	{
		$this->actingAs(2);

		$this->release->requestRelease(1);

		static::assertSame('ready', $this->releaseStatus(1));
	}

	public function testNobodyAsksForTheReleaseWhileItIsOff(): void
	{
		$this->actingAs(2);

		$this->expectException(TranslatableModuleException::class);
		$this->buildRelease(false)->requestRelease(1);
	}

	public function testAUserWithoutRightsOnThePlaylistDoesNotAskForItsRelease(): void
	{
		$this->actingAs(3);

		try
		{
			$this->release->requestRelease(1);
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('in_progress', $this->releaseStatus(1));
		}
	}

	public function testTheOwnerDoesNotExportWhileTheReleaseIsOn(): void
	{
		$this->actingAs(2);

		try
		{
			$this->exportService->exportToSmil(1);
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertFileDoesNotExist($this->workspace->absolute('/var/smil/playlists/1/items.smil'));
			static::assertSame('0000-00-00 00:00:00', $this->connection->fetchOne('SELECT export_time FROM playlists WHERE playlist_id = 1'));
		}
	}

	public function testTheAdminExportsAndReleasesThePlaylist(): void
	{
		$this->actingAs(2);
		$this->release->requestRelease(1);
		$this->actingAs(1);

		$this->exportService->exportToSmil(1);

		static::assertFileExists($this->workspace->absolute('/var/smil/playlists/1/items.smil'));
		static::assertSame('released', $this->releaseStatus(1));
		static::assertSame(68, (int) $this->connection->fetchOne('SELECT duration FROM playlists WHERE playlist_id = 1'));
	}

	public function testWhileTheReleaseIsOffTheOwnerExportsAndTheStatusIsKept(): void
	{
		$this->actingAs(2);

		$this->buildExportService($this->workspace, $this->buildRelease(false))->exportToSmil(1);

		static::assertSame('released', $this->releaseStatus(1));
	}

	public function testAChangeOfTheItemsStartsTheReleaseAgain(): void
	{
		$this->actingAs(2);
		$this->release->requestRelease(1);

		$this->container->get(ItemsService::class)->delete(1, 1);

		static::assertSame('in_progress', $this->releaseStatus(1));
	}

	public function testAChangeOfThePlaylistStartsTheReleaseAgain(): void
	{
		$this->actingAs(2);
		$this->release->requestRelease(1);

		$this->container->get(PlaylistsService::class)->toggleShuffle(1);

		static::assertSame('in_progress', $this->releaseStatus(1));
	}

	public function testTheModuleAdminCountsThePlaylistsWhichWait(): void
	{
		$this->actingAs(2);
		$this->release->requestRelease(1);
		$this->release->requestRelease(3);

		$this->actingAs(1);

		static::assertSame(2, $this->release->countWaiting());
	}

	public function testOnlyTheModuleAdminSeesTheDashboard(): void
	{
		$this->actingAs(2);
		$this->release->requestRelease(1);
		$dashboard = new PlaylistsDashboard($this->release, $this->container->get(Translator::class));

		static::assertSame('', $dashboard->renderContent());

		$this->actingAs(1);
		$label = $this->container->get(Translator::class)->translate('count_release_ready', 'playlists');
		static::assertStringContainsString('<strong>' . $label . ':</strong><span><a href="/playlists?release_status=ready">1</a></span>', $dashboard->renderContent());
	}

	public function testNobodySeesTheDashboardWhileTheReleaseIsOff(): void
	{
		$this->actingAs(1);

		static::assertNull($this->buildRelease(false)->countWaiting());
		static::assertSame('', $this->container->get(PlaylistsDashboard::class)->renderContent());
	}

	private function buildRelease(bool $enabled): PlaylistRelease
	{
		return new PlaylistRelease(
			$enabled,
			$this->container->get(PlaylistsService::class),
			$this->container->get(AclValidator::class),
			$this->container->get(UserSession::class)
		);
	}

	private function releaseStatus(int $playlistId): string
	{
		return (string) $this->connection->fetchOne('SELECT release_status FROM playlists WHERE playlist_id = ?', [$playlistId]);
	}
}
