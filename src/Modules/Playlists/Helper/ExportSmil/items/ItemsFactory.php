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

namespace App\Modules\Playlists\Helper\ExportSmil\items;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use App\Modules\Playlists\Helper\ItemType;

/**
 * Factory class responsible for creating items of various types.
 */
class ItemsFactory
{
	public const string MEDIA_TYPE_IMAGE       = 'image';
	public const string MEDIA_TYPE_VIDEO       = 'video';
	public const string MEDIA_TYPE_AUDIO       = 'audio';
	public const string MEDIA_TYPE_WIDGET      = 'widget';
	public const string MEDIA_TYPE_DOWNLOAD    = 'download';
	public const string MEDIA_TYPE_APPLICATION = 'application';
	public const string MEDIA_TYPE_TEXT        = 'text';

	private Config $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	/**
	 * @param array<string,mixed> $item
	 * @throws CoreException|ModuleException
	 */
	public function createItem(array $item): ItemInterface
	{
		return match ($item['item_type'])
		{
			ItemType::MEDIAPOOL->value, ItemType::MEDIA_EXTERN->value => $this->createMedia($item),
			ItemType::PLAYLIST->value => new SeqContainer(
				$this->config,
				$item,
				new Properties($this->config, $item['properties']),
				new Trigger($item['begin_trigger']),
				new Trigger($item['end_trigger']),
				new Conditional($item['conditional'])
			),
			default => throw new ModuleException('playlists_items', 'Unsupported item type: ' . $item['item_type'] . '.'),
		};
	}

	/**
	 * @param array<string,mixed> $item
	 * @throws CoreException|ModuleException
	 */
	private function createMedia(array $item): Media
	{
		$mediaType = explode('/', $item['mimetype'], 2)[0];

		return match ($mediaType)
		{
			self::MEDIA_TYPE_IMAGE => new Image(
				$this->config,
				$item,
				new Properties($this->config, $item['properties']),
				new Trigger($item['begin_trigger']),
				new Trigger($item['end_trigger']),
				new Conditional($item['conditional'])
			),
			self::MEDIA_TYPE_VIDEO => new Video(
				$this->config,
				$item,
				new Properties($this->config, $item['properties']),
				new Trigger($item['begin_trigger']),
				new Trigger($item['end_trigger']),
				new Conditional($item['conditional'])
			),
			self::MEDIA_TYPE_AUDIO => new Audio(
				$this->config,
				$item,
				new Properties($this->config, $item['properties']),
				new Trigger($item['begin_trigger']),
				new Trigger($item['end_trigger']),
				new Conditional($item['conditional'])
			),
			self::MEDIA_TYPE_WIDGET, self::MEDIA_TYPE_DOWNLOAD, self::MEDIA_TYPE_APPLICATION => new Widget(
				$this->config,
				$item,
				new Properties($this->config, $item['properties']),
				new Trigger($item['begin_trigger']),
				new Trigger($item['end_trigger']),
				new Conditional($item['conditional'])
			),
			self::MEDIA_TYPE_TEXT => new Text(
				$this->config,
				$item,
				new Properties($this->config, $item['properties']),
				new Trigger($item['begin_trigger']),
				new Trigger($item['end_trigger']),
				new Conditional($item['conditional'])
			),
			default => throw new ModuleException('playlists_items', 'Unsupported media type: ' . $mediaType.'.'),
		};
	}
}