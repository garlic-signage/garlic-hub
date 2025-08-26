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
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * Handles the processing and validation of audio files within the media pool.
 * This includes checking file size limits, setting metadata, deriving audio duration,
 * and creating appropriate thumbnail representations.
 */
class Audio extends AbstractMediaHandler
{
	private Ffmpeg $ffmpeg;
	private int $maxAudioSize;

	/**
	 * @throws CoreException
	 */
	public function __construct(Config $config, Filesystem $fileSystem, Ffmpeg $ffmpeg)
	{
		parent::__construct($config, $fileSystem); // should be first

		$this->ffmpeg       = $ffmpeg;
		$this->maxAudioSize = (int) $this->config->getConfigValue('audios', 'mediapool', 'max_file_sizes');
	}

	/**
	 * @throws ModuleException
	 */
	public function checkFileBeforeUpload(int $size): void
	{
		if ($size > $this->maxAudioSize)
			throw new ModuleException('mediapool', 'Filesize: '.$this->calculateToMegaByte($size).' MB exceeds max image size.');
	}

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
		if ($this->fileSize > $this->maxAudioSize)
			throw new ModuleException('mediapool', 'After Upload Check: '.$this->calculateToMegaByte($this->fileSize).' MB exceeds max audio size.');

		$this->ffmpeg->setMetadata($this->getMetadata());
		$this->ffmpeg->init($filePath);

		$this->setMetadata($this->ffmpeg->getMetadata());
		$this->duration = $this->ffmpeg->getDuration();

		$this->dimensions = [];
	}

	/**
	 * @throws FilesystemException
	 */
	public function createThumbnail(string $filePath): void
	{
		$fileInfo = pathinfo($filePath);
		$thumbPath = '/'.$this->thumbPath.'/'.$fileInfo['filename'].'.svg';
		$iconPath  = '/'.$this->iconsPath.'/audio.svg';
		$this->thumbExtension = 'svg';

		$this->filesystem->copy($iconPath, $thumbPath);
	}
}