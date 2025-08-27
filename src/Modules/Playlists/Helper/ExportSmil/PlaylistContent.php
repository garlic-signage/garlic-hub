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

namespace App\Modules\Playlists\Helper\ExportSmil;


use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemsFactory;
use App\Modules\Playlists\Helper\ExportSmil\items\Media;
use App\Modules\Playlists\Helper\ExportSmil\items\SeqContainer;
use App\Modules\Playlists\Helper\ItemDatasource;
use App\Modules\Playlists\Helper\ItemFlags;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMode;

/**
 * The `PlaylistContent` class is responsible for managing and constructing playlist content,
 * including handling media items, external media, and sub-playlists. This class provides methods
 * to initialize, build, and manipulate the playlist data and its representations.
 */
class PlaylistContent
{
	private readonly ItemsFactory $itemsFactory;
	private readonly Config $config;
	private string $contentElements;
	private string $contentPrefetch;
	private string $contentExclusive;
	private int $countEnabled = 0;
	/** @var array<string,mixed>  */
	private array $playlist  = [];
	/** @var list<array<string,mixed>>  */
	private array $items = [];
	/** @var array<int,int>|array<empty,empty>  */
	private array $touchTrigger = [];

	public function __construct(ItemsFactory $itemsFactory, Config $config)
	{
		$this->itemsFactory     = $itemsFactory;
		$this->config           = $config;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @param list<array<string,mixed>> $items
	 * @return $this
	 */
	public function init(array $playlist, array $items): static
	{
		$this->playlist     = $playlist;
		$this->items        = $items;
		$this->countEnabled = 0;
		$this->contentElements     = '';
		$this->contentPrefetch     = '';
		$this->contentExclusive    = '';

		return $this;
	}

	public function getContentElements(): string
	{
		return $this->contentElements;
	}

	public function getContentPrefetch(): string
	{
		return $this->contentPrefetch;
	}

	public function getContentExclusive(): string
	{
		return $this->contentExclusive;
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 */
	public function build(): static
	{
		$this->touchTrigger = [];
		// prepare Source for Touch trigger
		foreach ($this->items as $item)
		{
			if (array_key_exists('touches', $item['begin_trigger']))
			{
				foreach ($item['begin_trigger']['touches'] as $touch)
				{
					$this->touchTrigger[(int)$touch['touch_item_id']] = (int)$item['item_id'];
				}
			}
		}

		foreach ($this->items as $item)
		{
			switch ($item['item_type'])
			{
				case ItemType::MEDIAPOOL->value;
					$this->buildMedia($item);
					break;

				case ItemType::MEDIA_EXTERN->value:
					$this->buildMediaExternal($item);
					break;

				case ItemType::PLAYLIST->value:
					$this->buildPlaylist($item);
					break;
/*
				case ItemType::PLAYLIST_EXTERN->value:
					$this->buildPlaylistExternal($item);
					break;

				case ItemType::TEMPLATE->value:
					$this->buildTemplate($item);
					break;

				case ItemType::CHANNEL->value:
					$this->buildChannel($item);
					break;
*/
				default:
					throw new ModuleException('playlists_items', 'Unknown item type. Given: ' . $item['item_type'] . ' with item id: ' . $item['item_id'].'.');
			}
		}

		$this->addShuffle();

		return $this;
	}

	/**
	 * @param array<string,mixed> $itemData
	 * @throws CoreException
	 * @throws ModuleException
	 */
	private function buildMedia(array $itemData): void
	{
		/** @var Media $item */
		$item = $this->itemsFactory->createItem($itemData);
		$item->setBelongsToMasterPlaylist($this->playlist['playlist_mode'] === PlaylistMode::MASTER);
		$item->setTouches($this->touchTrigger);
		$serverUrl = $this->config->getConfigValue('content_server_url', 'mediapool');

		$originalPath = $this->config->getConfigValue('originals', 'mediapool', 'directories');
		$link = $serverUrl.'/'.str_replace('public/', '', $originalPath).'/'.
				$itemData['file_resource'].'.'. $itemData['extension'];

		$item->setLink($link);

		$this->addContentParts($itemData, $item->getSmilElementTag(), $item->getPrefetchTag(), $item->getExclusive());
	}

	/**
	 * @param array<string,mixed> $itemData
	 * @throws CoreException|ModuleException
	 */
	private function buildMediaExternal(array $itemData): void
	{
		/** @var Media $item */
		$item = $this->itemsFactory->createItem($itemData);
		$item->setBelongsToMasterPlaylist($this->playlist['playlist_mode'] === PlaylistMode::MASTER);
		$item->setTouches($this->touchTrigger);
		$contentData = @unserialize($itemData['content_data']);
		$item->setLink(str_replace('&', '&amp;', $contentData['url']));

		$this->addContentParts($itemData, $item->getSmilElementTag(), $item->getPrefetchTag(), $item->getExclusive());
	}

	/**
	 * @param array<string,mixed> $itemData
	 * @throws CoreException|ModuleException
	 */
	private function buildPlaylist(array $itemData): void
	{
		/** @var SeqContainer $item */
		$item = $this->itemsFactory->createItem($itemData);
		$item->setBelongsToMasterPlaylist($this->playlist['playlist_mode'] === PlaylistMode::MASTER);

		$this->addContentParts($itemData, $item->getSmilElementTag(), $item->getPrefetchTag(), $item->getExclusive());
	}

/*
	private function buildPlaylistExternal(array $itemData): void
	{
		$item = $this->itemsFactory->createItem($itemData);
		$item->setBelongsToMasterPlaylist($this->playlist['playlist_mode'] === PlaylistMode::MASTER);

		$this->addContentParts($itemData, $item->getElementLink(), '', '');
	}

	private function buildTemplate(array $itemData): void
	{
		$item = $this->itemsFactory->createItem($itemData);
		$item->setBelongsToMasterPlaylist($this->playlist['playlist_mode'] === PlaylistMode::MASTER);
		$item->setPlaylistPath($this->export_base_path.$this->playlist['playlist_id'].'/'); // do the link to media inside class

		$this->addContentParts($itemData, $item->getSmilElementTag(), $item->getPrefetchTag(), $item->getExclusive());
	}

	private function buildChannel(array $itemData): void
	{
		$item = $this->itemsFactory->createItem($itemData);
		$item->setBelongsToMasterPlaylist($this->playlist['playlist_mode'] === PlaylistMode::MASTER);

		$this->addContentParts($itemData, $item->getSmilElementTag(), $item->getPrefetchTag(), $item->getExclusive());
	}
*/
	private function addShuffle(): void
	{
		if ($this->playlist['shuffle'] == 0 || $this->countEnabled == 0)
			return;

		// make sure that the picking value is always <= than enabled media
		$picking = min($this->countEnabled, $this->playlist['shuffle_picking']);

		if ($picking == 0)
			$shuffle = Base::TABSTOPS_TAG.'<metadata><meta name="adapi:pickingAlgorithm" content="shuffle"/></metadata>'."\n";
		else
			$shuffle = Base::TABSTOPS_TAG.'<metadata>'."\n"
				.Base::TABSTOPS_PARAMETER.'<meta name="adapi:pickingAlgorithm" content="shuffle"/>'."\n"
				.Base::TABSTOPS_PARAMETER.'<meta name="adapi:pickingBehavior" content="pickN"/>'."\n"
				.Base::TABSTOPS_PARAMETER.'<meta name="adapi:pickNumber" content="'.$picking.'"/>'."\n"
				.Base::TABSTOPS_TAG.'</metadata>'."\n";

		$this->contentElements  = $shuffle . $this->contentElements;
	}

	/**
	 * this handles the feature that we want sometimes disabled items in prefetch
	 * and sometimes not.
	 * see comments, where the cases are explained
	 * The default is: add prefetch if the item is disabled, add all other parts, if not disabled
	 *
	 * @param array<string,mixed> $item
	 */
	private function addContentParts(array $item, string $element, string $prefetch, string $exclusive): void
	{
		$disabled = ($item['flags'] & ItemFlags::disabled->value) > 0;

		switch ($item['item_type'])
		{
			case ItemType::MEDIAPOOL->value:
//			case ItemType::CHANNEL->value:
			case ItemType::PLAYLIST->value:
//			case ItemType::PLAYLIST_EXTERN->value:
				$this->contentPrefetch .= $prefetch;
				break;

			case ItemType::MEDIA_EXTERN->value:
				// no streams or websites in prefetch
				if ($item['datasource'] !== ItemDatasource::STREAM->value && $item['mimetype'] !== 'text/html')
					$this->contentPrefetch .= $prefetch;

				break;
	/*		case ItemType::TEMPLATE->value:
				// don't export prefetch on templates, if template is HTML and save_format is HTML (not WGT)
				if ($item['template_mimetype'] != 'text/html' && $item['website_save_format'] !== 'html')
					$this->contentPrefetch .= $prefetch;
				break;
*/
			// default isn't required as we check in build method for valid ItemTypes and throw an exception
		}

		if (!$disabled)
		{
			$this->countEnabled++;
			$this->contentElements  .= $element;
			$this->contentExclusive .= $exclusive;
		}
	}
}