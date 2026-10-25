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

namespace Tests\Integration\Modules\Playlists;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Config\Config;
use App\Framework\Media\DeliveryUrlBuilder;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemsFactory;
use App\Modules\Playlists\Helper\ExportSmil\LocalWriter;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\ExportItemsLoader;
use App\Modules\Playlists\Services\ExportService;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistMetricsUpdater;
use App\Modules\Playlists\Services\PlaylistsService;
use Psr\Log\NullLogger;
use Tests\Integration\Support\MediaWorkspace;

/**
 * For the tests (of ContainerTestCase) which export a playlist: the ExportService like in config/services/playlists.php,
 * but the files go into the workspace.
 */
trait BuildsExportService
{
	private function buildExportService(MediaWorkspace $workspace): ExportService
	{
		$itemsRepository  = $this->container->get(ItemsRepository::class);
		$playlistsService = $this->container->get(PlaylistsService::class);
		$calculator       = $this->container->get(PlaylistMetricsCalculator::class);
		$config           = $this->container->get(Config::class);

		return new ExportService(
			$workspace->config,
			$playlistsService,
			new ExportItemsLoader($itemsRepository, $calculator),
			new PlaylistMetricsUpdater($itemsRepository, $playlistsService, $calculator, new NullLogger()),
			$this->container->get(Transactions::class),
			new LocalWriter($workspace->config, $workspace->filesystem),
			new PlaylistContent(
				new ItemsFactory($config),
				new DeliveryUrlBuilder($config->getConfigValue('url', 'mediapool', 'content_server')),
				$this->container->get(MimeTypeExtensionMapper::class)
			),
			new NullLogger()
		);
	}
}
