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

namespace App\Modules\Playlists\Services\InsertItems;

use App\Framework\Core\Config\Config;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Playlists\Services\WidgetsService;
use App\Modules\Templates\Services\TemplatesService;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;

readonly class InsertItemFactory
{


	public function __construct(private MediaService $mediaService,
								private ItemsRepository $itemsRepository,
								private PlaylistsService $playlistsService,
								private TemplatesService $templateService,
								private Config $config,
								private Filesystem $fileSystem,
								private PlaylistMetricsCalculator $playlistMetricsCalculator,
								private WidgetsService $widgetsService,
								private  LoggerInterface $logger)
	{
	}

	public function create(string $source): ?AbstractInsertItem
	{
		return match ($source)
		{
			'mediapool' => new Media($this->itemsRepository, $this->mediaService, $this->playlistsService, $this->playlistMetricsCalculator, $this->widgetsService, $this->logger),
			'playlist' => new Playlist($this->itemsRepository, $this->playlistsService, $this->playlistMetricsCalculator, $this->logger),
			'template' => new Template(
				$this->itemsRepository,
				$this->templateService,
				$this->config,
				$this->fileSystem,
				$this->playlistsService,
				$this->playlistMetricsCalculator,
				$this->logger),
			default => null,
		};
	}

}