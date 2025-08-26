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

namespace App\Modules\Mediapool\Utils;


use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\Ffmpeg;
use Imagick;
use ImagickException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * This class handles video file operations, including upload validation, metadata extraction,
 * and thumbnail generation. It extends the AbstractMediaHandler class to integrate with the
 * existing media handling framework.
 */
class Video extends AbstractMediaHandler
{
	private Imagick $imagick;
	private Ffmpeg $ffmpeg;
	private int $maxVideoSize;

	/**
	 * @throws CoreException
	 */
	public function __construct(Config $config, Filesystem $fileSystem, Ffmpeg $ffmpeg, Imagick $imagick)
	{
		parent::__construct($config, $fileSystem); // should be first

		$this->ffmpeg       = $ffmpeg;
		$this->imagick      = $imagick;
		$this->maxVideoSize = (int) $this->config->getConfigValue('videos', 'mediapool', 'max_file_sizes');
	}

	/**
	 * @throws ModuleException
	 */
	public function checkFileBeforeUpload(int $size): void
	{
		if ($size > $this->maxVideoSize)
			throw new ModuleException('mediapool', 'Filesize: '.$this->calculateToMegaByte($size).' MB exceeds max image size.');	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	public function checkFileAfterUpload(string $filePath): void
	{
		if (!$this->filesystem->fileExists($filePath))
			throw new ModuleException('mediapool', 'After Upload Check: '.$filePath.' not exists.');

		$this->fileSize = $this->filesystem->fileSize($filePath);
		if ($this->fileSize > $this->maxVideoSize)
			throw new ModuleException('mediapool', 'After Upload Check: '.$this->calculateToMegaByte($this->fileSize).' MB exceeds max image size.');

		$this->ffmpeg->setMetadata($this->getMetadata());
		$this->ffmpeg->init($filePath);
		$mediaProperties = $this->ffmpeg->getMediaProperties();
		if ($mediaProperties['width'] > $this->maxWidth)
			throw new ModuleException('mediapool', 'After Upload Check:  Video width '.$mediaProperties['width'].' exceeds maximum.');

		if ($mediaProperties['height'] > $this->maxHeight)
			throw new ModuleException('mediapool', 'After Upload Check:  Video height '.$mediaProperties['height'] .' exceeds maximum.');

		$this->setMetadata($this->ffmpeg->getMetadata());
		$this->duration   = $this->ffmpeg->getDuration();

		$this->dimensions = ['width' => $mediaProperties['width'] , 'height' => $mediaProperties['height'] ];
	}

	/**
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ImagickException
	 */
	public function createThumbnail(string $filePath): void
	{
		$vidcapPath = $this->ffmpeg->createVideoThumbnail('/'.$this->originalPath);

		$fileInfo = pathinfo($filePath);
		$thumbPath = '/'.$this->thumbPath.'/'.$fileInfo['filename'].'.jpg';
		$this->filesystem->move($vidcapPath, $thumbPath);

		$absolutePath = $this->getAbsolutePath($thumbPath);
		$this->imagick->readImage($absolutePath);
		$this->imagick->thumbnailImage($this->thumbWidth, $this->thumbHeight, true);
		$this->imagick->writeImage($absolutePath);
	}
}