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

namespace App\Modules\Mediapool\Utils;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Media\Ffmpeg;
use Exception;
use Imagick;
use ImagickException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * The image of a medium which the AI looks at for the tagging: made from the original, large enough for texts and
 * details, and only for the request, it is not stored. A video gives the frame of its thumbnail, a document its
 * first page. SVG is left out: reading it may load other files.
 */
class TaggingImage
{
	public const int MAX_SIZE = 1024;

	public function __construct(
		private readonly Config $config,
		private readonly Filesystem $filesystem,
		private readonly ImagickFactory $imagickFactory,
		private readonly Ffmpeg $ffmpeg
	) {}

	public static function supports(string $mimetype): bool
	{
		if ($mimetype === 'image/svg+xml')
			return false;

		return str_starts_with($mimetype, 'image/') || str_starts_with($mimetype, 'video/') || $mimetype === 'application/pdf';
	}

	/**
	 * @param string $originalPath relative to the system directory, e.g. var/storage/mediapool/originals/x.jpg
	 * @return string the image as data url of a jpeg
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ImagickException
	 */
	public function create(string $originalPath, string $mimetype): string
	{
		if (str_starts_with($mimetype, 'video/'))
			return $this->createFromVideo($originalPath);

		return $this->createFromFile($this->absolute($originalPath), $mimetype === 'application/pdf');
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ImagickException
	 */
	private function createFromVideo(string $originalPath): string
	{
		// a directory of its own: two requests at the same time must not take the frame of the other
		$directory = $this->config->getConfigValue('uploads', 'mediapool', 'directories') . '/tagging-' . bin2hex(random_bytes(8));
		$this->filesystem->createDirectory($directory);
		try
		{
			$this->ffmpeg->init('/' . $originalPath);
			$frame = $this->ffmpeg->createVideoThumbnail('/' . $directory);

			return $this->createFromFile($this->absolute($frame), false);
		}
		finally
		{
			$this->filesystem->deleteDirectory($directory);
		}
	}

	/**
	 * @throws ImagickException
	 */
	private function createFromFile(string $absolutePath, bool $isDocument): string
	{
		$imagick = $this->imagickFactory->createImagick();
		if ($isDocument)
			$imagick->setResolution(120, 120);
		$imagick->readImage($absolutePath . '[0]');

		// a transparent area becomes white instead of black
		$imagick->setImageBackgroundColor('white');
		$imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

		if ($imagick->getImageWidth() > self::MAX_SIZE || $imagick->getImageHeight() > self::MAX_SIZE)
			$imagick->thumbnailImage(self::MAX_SIZE, self::MAX_SIZE, true);

		$imagick->setImageFormat('jpeg');
		$imagick->setImageCompressionQuality(85);

		return 'data:image/jpeg;base64,' . base64_encode($imagick->getImageBlob());
	}

	private function absolute(string $path): string
	{
		return $this->config->getPaths('systemDir') . '/' . ltrim($path, '/');
	}
}
