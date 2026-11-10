<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Playlists\Services;

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Helper\Serialization;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Playlists\Helper\ExportSmil\LocalWriter;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Helper\ReleaseStatus;
use App\Modules\Playlists\Helper\TemplateItemName;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Throwable;

class ExportService extends AbstractBaseService
{
	private readonly Config $config;
	private readonly PlaylistsService $playlistsService;
	private readonly ExportItemsLoader $exportItemsLoader;
	private readonly PlaylistMetricsUpdater $playlistMetricsUpdater;
	private readonly Transactions $transactions;
	private readonly LocalWriter $localSmilWriter;
	private readonly PlaylistContent $playlistContent;
	private readonly TemplateItemName $templateItemName;
	private readonly PlaylistVersionsService $playlistVersionsService;
	private readonly PlaylistRelease $playlistRelease;

	public function __construct(Config $config, PlaylistsService $playlistsService, ExportItemsLoader $exportItemsLoader, PlaylistMetricsUpdater $playlistMetricsUpdater, Transactions $transactions, LocalWriter $localSmilWriter, PlaylistContent $playlistContent, TemplateItemName $templateItemName, PlaylistVersionsService $playlistVersionsService, PlaylistRelease $playlistRelease, LoggerInterface $logger )
	{
		$this->config          = $config;
		$this->playlistsService = $playlistsService;
		$this->exportItemsLoader = $exportItemsLoader;
		$this->playlistMetricsUpdater = $playlistMetricsUpdater;
		$this->transactions    = $transactions;
		$this->localSmilWriter = $localSmilWriter;
		$this->playlistContent = $playlistContent;
		$this->templateItemName = $templateItemName;
		$this->playlistVersionsService = $playlistVersionsService;
		$this->playlistRelease = $playlistRelease;

		// @infection-ignore-all the parent only keeps the logger
		parent::__construct($logger);
	}

	/**
	 * A multizone playlist exports nothing of its zones: the players get the zone playlists as they were exported
	 * last, with their own export (and release) only. A zone playlist which was never exported stops the export,
	 * see docs/multizone-export.md.
	 *
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function exportToSmil(int $playlistId): void
	{
		$this->transactions->begin();
		try
		{

			$playlist = $this->playlistsService->loadWithUserById($playlistId); // checks rights
			// while the release is on, the export is the release: only an admin does it
			if (!$this->playlistRelease->mayExport($playlist))
				throw new AccessDeniedException('playlists', 'playlist_export_not_allowed', ['playlist_id' => $playlistId]);

			$layout = Serialization::unserializeSecure($playlist['layout'] ?? null);
			if (($playlist['playlist_mode'] ?? '') === PlaylistMode::MULTIZONE->value && !empty($layout['zones']))
			{
				$metrics = ['filesize' => 0, 'duration' => 0, 'owner_duration' => 0];
				foreach ($layout['zones'] as $zone)
				{
					if ((int) ($zone['zone_playlist_id'] ?? 0) === 0)
						continue;

					// a viewer of a zone playlist may use it in his multizone playlist, but not export or release it
					$zonePlaylist = $this->playlistsService->loadUsableById((int) $zone['zone_playlist_id']);
					if (!$this->localSmilWriter->isExported((int) $zonePlaylist['playlist_id']))
						throw new TranslatableModuleException('playlists', 'zone_playlist_not_exported', ['playlist_name' => (string) $zonePlaylist['playlist_name']]);

					// the highest values of the zones as they were exported
					foreach (array_keys($metrics) as $key)
						$metrics[$key] = max($metrics[$key], (int) $zonePlaylist[$key]);
				}
			}
			else
				$metrics = $this->export($playlist);

			$metrics['release_status'] = ReleaseStatus::RELEASED->value;
			if ($this->playlistsService->updateExport($playlistId, $metrics) === 0)
				throw new TranslatableModuleException('playlists', 'playlist_export_failed', ['playlist_id' => $playlistId]);

			// the exported state is a version (while the versioning is on)
			$this->playlistVersionsService->recordExport($playlist);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	private function export(array $playlist): array
	{
		$results = $this->exportItemsLoader->loadByPlaylist($playlist, $this->config->getEdition());
		$this->playlistMetricsUpdater->updateItemsMetrics($playlist['playlist_id'], $results['playlist_metrics']);
		/*
			    1. Save metrics in the exported playlist (did with results as last, but doesn't matter)
				2. Save metrics in all items wich represent this playlist.
				3. find all playlists that have the exported playlist nested
				4. calculate metrics for the new playlists
				5. save metrics in new playlists
				6. save metrics in all items that represent the new playlist
				7. recurse to 3.
		 */
		$this->playlistMetricsUpdater->updateMetricsRecursively($playlist['playlist_id']);

		$this->playlistContent->init($playlist, $results['items'])->build();
		$this->localSmilWriter->initExport($playlist['playlist_id']);
		$this->localSmilWriter->writeSMILFiles($this->playlistContent);
		$this->nameLegacyTemplateImages($results['items']);

		return $results['playlist_metrics']->toPlaylistTableArray();
	}

	/**
	 * The players get the image of the exported content (see TemplateItemName). The images of former contents stay:
	 * a restored version of the playlist needs them, they go with the playlist (see PlaylistFiles).
	 * An image from before the update, named by the item id only, gets the name of the content.
	 *
	 * @param list<array<string,mixed>> $items
	 * @throws CoreException
	 */
	private function nameLegacyTemplateImages(array $items): void
	{
		$systemDir = $this->config->getPaths('systemDir');
		foreach ($items as $item)
		{
			if ($item['item_type'] !== ItemType::TEMPLATE->value)
				continue;

			// @infection-ignore-all the database gives numbers
			$itemId = (int) $item['item_id'];
			$name   = $this->templateItemName->build($itemId, (string) $item['content_data']);
			foreach (['originals', 'thumbnails'] as $key)
			{
				$directory = $systemDir . '/' . $this->config->getConfigValue($key, 'playlists', 'directories');
				// <item_id>.<ext>, not the files of the item 12 for the item 1
				foreach (glob($directory . '/' . $itemId . '.*') ?: [] as $file)
				{
					if (glob($directory . '/' . $name . '.*') === [])
						rename($file, $directory . '/' . $name . '.' . pathinfo($file, PATHINFO_EXTENSION));
				}
			}
		}
	}

}