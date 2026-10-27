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

namespace App\Modules\Playlists\Helper\Compose;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Media\MediaUrlSigner;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\TemplateItemName;

/**
 * Determines the thumbnail of a playlist item for the composer:
 * the local path and the url the browser loads it from.
 */
readonly class ItemThumbnailResolver
{
	public function __construct(
		private Config $config,
		private MimeTypeExtensionMapper $mimeTypeExtensionMapper,
		private MediaUrlSigner $mediaUrlSigner,
		private TemplateItemName $templateItemName
	) {}

	/**
	 * @param array<string,mixed> $item
	 * @return array{path:string, url:string}|null null for item types without a thumbnail
	 * @throws CoreException
	 */
	public function resolve(array $item): ?array
	{
		return match ($item['item_type'])
		{
			ItemType::MEDIAPOOL->value => $this->resolveMediapool($item),
			ItemType::PLAYLIST->value  => ['path' => 'public/images/icons/playlist.svg', 'url' => '/images/icons/playlist.svg'],
			ItemType::TEMPLATE->value  => $this->resolveTemplate((int) $item['item_id'], (string) ($item['content_data'] ?? '')),
			default                    => null,
		};
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array{path:string, url:string}
	 * @throws CoreException
	 */
	private function resolveMediapool(array $item): array
	{
		$thumbnailPath = $this->config->getConfigValue('thumbnails', 'mediapool', 'directories');

		$ext = 'jpg';
		if (str_starts_with($item['mimetype'], 'image/'))
			$ext = $this->mimeTypeExtensionMapper->determineExtension($item['mimetype']);

		$filename    = $item['file_resource'];
		$isSvgWidget = ($item['mimetype'] === 'application/widget' && $item['content_data'] == '');
		$finalExt    = $isSvgWidget ? 'svg' : $ext;

		// ugly workaround fix this later.
		if ($item['mimetype'] === 'application/widget'
			&& !file_exists($thumbnailPath . '/' . $filename . '.' . $finalExt))
			$finalExt = 'png';

		return [
			'path' => $thumbnailPath . '/' . $filename . '.' . $finalExt,
			// mediapool files are stored outside the document root
			'url'  => $this->mediaUrlSigner->thumbUrl($filename, $finalExt)
		];
	}

	/**
	 * The thumbnail of the saved content (see TemplateItemName), before the first export after an update
	 * the one named by the item id. It keeps the format of the saved image (jpg, png, webp, bmp).
	 * Without a file the preview is the jpg of the content.
	 *
	 * @return array{path:string, url:string}
	 * @throws CoreException
	 */
	private function resolveTemplate(int $itemId, string $content): array
	{
		$thumbDir = $this->config->getConfigValue('thumbnails', 'playlists', 'directories');
		$name     = $this->templateItemName->build($itemId, $content);
		foreach ([$name, (string) $itemId] as $candidateName)
		{
			foreach (['jpg', 'png', 'webp', 'bmp'] as $extension)
			{
				if (file_exists($thumbDir . '/' . $candidateName . '.' . $extension))
					return $this->templateThumbnail($thumbDir, $candidateName, $extension);
			}
		}

		return $this->templateThumbnail($thumbDir, $name, 'jpg');
	}

	/**
	 * @return array{path:string, url:string}
	 */
	private function templateThumbnail(string $thumbDir, string $name, string $extension): array
	{
		return [
			'path' => $thumbDir . '/' . $name . '.' . $extension,
			'url'  => $this->mediaUrlSigner->itemThumbUrl($name, $extension)
		];
	}
}
