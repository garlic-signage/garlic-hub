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

namespace App\Modules\Playlists\Helper\Compose;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Media\MediaUrlSigner;
use App\Framework\Helper\Serialization;
use App\Modules\Playlists\Helper\ItemType;

/**
 * Determines what the preview (lightbox) of a playlist item shows:
 * the original of a media, the image of a template, a widget with the preferences of the item,
 * the items of a nested playlist. Everything else shows its thumbnail.
 */
readonly class ItemPreviewResolver
{
	public const string TYPE_IMAGE    = 'image';
	public const string TYPE_VIDEO    = 'video';
	public const string TYPE_AUDIO    = 'audio';
	public const string TYPE_PDF      = 'pdf';
	public const string TYPE_WIDGET   = 'widget';
	public const string TYPE_PLAYLIST = 'playlist';

	public function __construct(private Config $config, private MediaUrlSigner $mediaUrlSigner) {}

	/**
	 * @param array<string,mixed> $item with the thumbnail path and url of ItemThumbnailResolver
	 * @return array{type:string, url:string}
	 * @throws CoreException
	 */
	public function resolve(array $item): array
	{
		// @infection-ignore-all the cast is for databases which give no text, ours gives text
		$thumbnail = ['type' => self::TYPE_IMAGE, 'url' => (string) ($item['urls']['thumbnail'] ?? '')];

		return match ($item['item_type'])
		{
			ItemType::MEDIAPOOL->value => $this->resolveMediapool($item) ?? $thumbnail,
			ItemType::PLAYLIST->value  => ['type' => self::TYPE_PLAYLIST, 'url' => ''],
			ItemType::TEMPLATE->value  => $this->resolveTemplate((string) ($item['paths']['thumbnail'] ?? '')) ?? $thumbnail,
			default                    => $thumbnail,
		};
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array{type:string, url:string}|null null: no preview of its own
	 */
	private function resolveMediapool(array $item): ?array
	{
		// @infection-ignore-all the cast is for databases which give no text, ours gives text
		$checksum = (string) $item['file_resource'];
		// @infection-ignore-all the cast is for databases which give no text, ours gives text
		$mimetype = (string) $item['mimetype'];

		if ($mimetype === 'application/widget')
			return ['type' => self::TYPE_WIDGET, 'url' => $this->mediaUrlSigner->widgetUrl($checksum, $this->determinePreferences($item))];

		// @infection-ignore-all the cast is for databases which give no text, ours gives text
		$extension = (string) ($item['extension'] ?? '');
		if ($extension === '')
			return null;

		$type = match (true)
		{
			str_starts_with($mimetype, 'image/') => self::TYPE_IMAGE,
			str_starts_with($mimetype, 'video/') => self::TYPE_VIDEO,
			str_starts_with($mimetype, 'audio/') => self::TYPE_AUDIO,
			$mimetype === 'application/pdf'      => self::TYPE_PDF,
			default                              => null
		};
		if ($type === null)
			return null;

		return ['type' => $type, 'url' => $this->mediaUrlSigner->originalUrl($checksum, $extension)];
	}

	/**
	 * The values of the item, like the export hands them to the player.
	 *
	 * @param array<string,mixed> $item
	 * @return array<string, scalar>
	 */
	private function determinePreferences(array $item): array
	{
		$contentData = $item['content_data'] ?? '';
		if (!is_array($contentData))
			$contentData = Serialization::unserializeSecure(is_string($contentData) ? $contentData : '');

		return array_filter($contentData, static fn(mixed $value): bool => is_scalar($value));
	}

	/**
	 * The original image of the template item: same name and format as its thumbnail.
	 *
	 * @return array{type:string, url:string}|null null: there is no original
	 * @throws CoreException
	 */
	private function resolveTemplate(string $thumbnailPath): ?array
	{
		$name      = pathinfo($thumbnailPath, PATHINFO_FILENAME);
		$extension = pathinfo($thumbnailPath, PATHINFO_EXTENSION);
		// @infection-ignore-all a file with an empty name or extension is never in the originals
		if ($name === '' || $extension === '')
			return null;

		$originals = $this->config->getConfigValue('originals', 'playlists', 'directories');
		if (!file_exists($originals . '/' . $name . '.' . $extension))
			return null;

		return ['type' => self::TYPE_IMAGE, 'url' => $this->mediaUrlSigner->itemOriginalUrl($name, $extension)];
	}
}
