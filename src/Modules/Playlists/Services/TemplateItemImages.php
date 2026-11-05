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

namespace App\Modules\Playlists\Services;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Shared\Versioning\UnitVersions;
use Doctrine\DBAL\Exception;

/**
 * The rendered images of the template items of a playlist (see TemplateItemName). The export keeps the images
 * of former contents, as a restored version needs them. They go with the playlist: a deleted playlist
 * can not be restored.
 */
readonly class TemplateItemImages
{
	public function __construct(private Config $config, private ItemsRepository $itemsRepository, private UnitVersions $unitVersions)
	{}

	/**
	 * The template items of the playlist and of its versions: a version still has the items deleted since.
	 * Before the playlist and its versions are deleted.
	 *
	 * @return list<int>
	 * @throws Exception
	 */
	public function findItemIds(int $playlistId): array
	{
		$items = $this->itemsRepository->findAllByPlaylistId($playlistId);
		foreach ($this->unitVersions->findVersions('playlists', $playlistId) as $version)
		{
			$snapshot = $this->unitVersions->findSnapshot('playlists', $playlistId, $version['version']);
			$items    = array_merge($items, array_filter((array) ($snapshot['items'] ?? []), 'is_array'));
		}

		$itemIds = [];
		foreach ($items as $item)
		{
			if (($item['item_type'] ?? '') === ItemType::TEMPLATE->value && is_numeric($item['item_id'] ?? null))
				$itemIds[] = (int) $item['item_id'];
		}

		return array_values(array_unique($itemIds));
	}

	/**
	 * After the commit: if a file can not be removed, only an orphan is left.
	 *
	 * @param list<int> $itemIds
	 * @return list<string> the files which could not be removed
	 * @throws CoreException
	 */
	public function remove(array $itemIds): array
	{
		$systemDir = $this->config->getPaths('systemDir');
		$failed    = [];
		foreach (['originals', 'thumbnails'] as $key)
		{
			$directory = $systemDir . '/' . $this->config->getConfigValue($key, 'playlists', 'directories');
			foreach ($itemIds as $itemId)
			{
				// <item_id>.<ext> and <item_id>-<hmac>.<ext>, not the files of the item 12 for the item 1
				foreach (glob($directory . '/' . $itemId . '[.-]*') ?: [] as $file)
				{
					if (!@unlink($file))
						$failed[] = $file;
				}
			}
		}

		return $failed;
	}
}
