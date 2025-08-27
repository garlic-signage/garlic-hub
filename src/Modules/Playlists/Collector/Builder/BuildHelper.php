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

namespace App\Modules\Playlists\Collector\Builder;

use App\Modules\Playlists\Collector\Contracts\ContentReaderInterface;
use App\Modules\Playlists\Collector\Contracts\ExternalContentReaderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The BuildHelper class is designed to collect and manipulate playlist content, including items, prefetches,
 * and exclusive content. It interacts with internal and external content sources and utilizes a logger for error handling.
 */
class BuildHelper
{
	private ContentReaderInterface $contentReader;
	private ExternalContentReaderInterface $externalContentReader;
	private LoggerInterface $logger;

	public function __construct(ContentReaderInterface $contentReader, ExternalContentReaderInterface $externalContentReader, LoggerInterface $logger
	)
	{
		$this->contentReader = $contentReader;
		$this->externalContentReader = $externalContentReader;
		$this->logger = $logger;
	}

	public function collectItems(int $playlistId): string
	{
		try
		{
			$items      = $this->contentReader->init($playlistId)->loadPlaylistItems();
			$countMatch = preg_match_all('/{ITEMS_.*?}/', $items, $placeholders);
			if ($countMatch > 0)
			{
				foreach ($placeholders[0] as $value)
				{
					$subPlaylistId = $this->parsePlaylistPlaceholder($value);

					if (is_int($subPlaylistId) && $subPlaylistId > 0)
					{
						$recurse = $this->collectItems((int) $subPlaylistId);
						if (!empty($recurse))
							$items = str_replace('{ITEMS_'.$subPlaylistId.'}', "\n".$recurse."\n", $items);
						else
							$items = str_replace('{ITEMS_'.$subPlaylistId.'}', '', $items);
					}
					else
					{
						// get Playlist url from value after #
						$url          = (string) $subPlaylistId;
						$externalData = $this->externalContentReader->init($url)->loadPlaylistItems();
						$items        = str_replace('{ITEMS_0#'.$url.'}', $externalData, $items);
					}
				}
			}
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error recurse items: ' . $e->getMessage());
			$items = '';
		}
		return $items;
	}

	public function collectPrefetches(int $playlistId): string
	{
		try
		{
			$prefetches = $this->contentReader->init($playlistId)->loadPlaylistPrefetch();
			$countMatch = preg_match_all('/{PREFETCH_.*?}/', $prefetches, $placeholders); // check if there are any nested container with change greediness when more than one PREFETCH in one line happens
			if ($countMatch > 0)
			{
				foreach ($placeholders[0] as $placeholder)
				{
					$subPlaylistId = $this->parsePlaylistPlaceholder($placeholder);
					$recurse = $this->collectPrefetches((int) $subPlaylistId);
					$prefetches = str_replace('{PREFETCH_' . $subPlaylistId . '}', $recurse, $prefetches);
				}
			}
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error recurse prefetches: ' . $e->getMessage());
			$prefetches = '';
		}
		return $prefetches;
	}

	public function collectExclusives(int $playlistId, string $exclusive = ''): string
	{
		try
		{
			$exclusive .= $this->contentReader->init($playlistId)->loadPlaylistExclusive();
			$countMatch = preg_match_all('/{ITEMS_.*}/', $exclusive, $placeholders);
			if ($countMatch > 0)
			{
				foreach ($placeholders[0] as $placeholder)
				{
					$subPlaylistId = $this->parsePlaylistPlaceholder($placeholder);
					$exclusive     = str_replace('{ITEMS_' . $subPlaylistId . '}', $this->contentReader->init((int) $subPlaylistId)->loadPlaylistItems(), $exclusive);
					$exclusive     = $this->collectExclusives((int) $subPlaylistId, $exclusive); // check if there is something nested
				}
			}
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error recurse exclusive: ' . $e->getMessage());
			$exclusive = '';
		}

		return $exclusive;
	}


	protected function parsePlaylistPlaceholder(string $placeholder): string|int
	{
		if (str_contains($placeholder, '#'))
		{
			// External URL
			$parts = explode('#', $placeholder);
			return substr($parts[1], 0, -1);
		}

		// Internal ID
		return (int)explode('_', $placeholder)[1];
	}

}