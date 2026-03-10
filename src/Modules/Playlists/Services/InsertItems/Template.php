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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Templates\Services\TemplatesService;
use Doctrine\DBAL\Exception;
use League\Flysystem\Filesystem;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;

class Template extends AbstractInsertItem
{
	public function __construct(ItemsRepository $itemsRepository,
								private readonly TemplatesService $templatesService,
								private readonly Config $config,
								private readonly Filesystem $fileSystem,
								PlaylistsService $playlistsService,
								PlaylistMetricsCalculator $playlistMetricsCalculator,
								LoggerInterface $logger)
	{
		$this->itemsRepository  = $itemsRepository;
		$this->playlistsService = $playlistsService;
		$this->playlistMetricsCalculator = $playlistMetricsCalculator;

		parent::__construct($logger);
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function insert(int $playlistId, string|int $insertId, int $position): array
	{
		try
		{
			$this->itemsRepository->beginTransaction();

			$playlistData = $this->checkPlaylistAcl($playlistId);

			$template = $this->templatesService->loadWithUserById((int) $insertId); // checks rights, too
			if (empty($template))
				throw new ModuleException('items', 'Template is not accessible');

			/*			if (!$this->allowedByTimeLimit($playlistId, $playlistData['time_limit']))
							throw new ModuleException('items', 'Playlist time limit exceeds');
			*/

			$dur          = (int) $this->config->getConfigValue('duration', 'playlists', 'Defaults');

			$itemDuration =  $this->playlistMetricsCalculator->calculateRemainingDuration($playlistData, $dur);
			$this->itemsRepository->updatePositionsWhenInserted($playlistId, $position);


			$saveItem = [
				'playlist_id'   => $playlistId,
				'datasource'    => 'file',
				'UID'           => $this->UID,
				'item_duration' => $itemDuration,
				'item_filesize' => 0,
				'item_order'    => $position,
				'item_name'     => $template['name'],
				'item_type'     => ItemType::TEMPLATE->value,
				'file_resource' => $insertId,
				'content_data'  => $template['content'],
				'mimetype'      => 'image/jpeg'
			];
			$id = $this->itemsRepository->insert($saveItem);
			if ($id === 0)
				throw new ModuleException('items', 'Template item could not inserted.');

			$fileNameTemplate     = $insertId.'.jpg';
			$fileNameItem         = $id.'.jpg';
			$thumbPathTemplate    = $this->config->getConfigValue('thumbnails', 'templates', 'directories').'/'.$fileNameTemplate;
			$orginalPathTemplate  = $this->config->getConfigValue('originals', 'templates', 'directories').'/'.$fileNameTemplate;
			$thumbPathItem        = $this->config->getConfigValue('thumbnails', 'playlists', 'directories').'/'.$fileNameItem;
			$orginalPathItem      = $this->config->getConfigValue('originals', 'playlists', 'directories').'/'.$fileNameItem;;

			$this->fileSystem->copy($orginalPathTemplate, $orginalPathItem);
			$this->fileSystem->copy($thumbPathTemplate, $thumbPathItem);
			$saveItem['item_id'] = $id;
			$saveItem['paths']['thumbnail'] = $thumbPathItem;

			$playlistMetrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlistData)->getMetricsForFrontend();

			$this->itemsRepository->commitTransaction();

			return ['playlist_metrics' => $playlistMetrics, 'item' => $saveItem];
		}
		catch (Exception | ModuleException | CoreException | PhpfastcacheSimpleCacheException $e)
		{
			$this->itemsRepository->rollBackTransaction();
			$this->logger->error('Error insert playlist: ' . $e->getMessage());
			return [];
		}
	}


}