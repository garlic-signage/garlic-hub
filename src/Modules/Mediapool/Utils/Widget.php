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
use App\Framework\Utils\Widget\ConfigXML;
use Imagick;
use ImagickException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class Widget extends AbstractMediaHandler
 *
 * This class handles functionalities related to managing widgets, including
 * checking file size constraints before and after uploads, and creating thumbnails.
 */
class Widget extends AbstractMediaHandler
{
	private int $maxDownloadSize;
	private ZipFilesystemFactory $zipFilesystemFactory;
	private Imagick $imagick;
	private ConfigXML $configXML;

	/**
	 * @throws CoreException
	 */
	public function __construct(Config $config, Filesystem $fileSystem, ZipFilesystemFactory $zipFilesystemFactory, Imagick $imagick, ConfigXML $configXML)
	{
		parent::__construct($config, $fileSystem); // should be first

		$this->zipFilesystemFactory = $zipFilesystemFactory;
		$this->imagick              = $imagick;
		$this->configXML            = $configXML;
		$this->maxDownloadSize      = (int) $this->config->getConfigValue('downloads', 'mediapool', 'max_file_sizes');
	}

	/**
	 * @throws ModuleException
	 */
	public function checkFileBeforeUpload(int $size): void
	{
		if ($size > $this->maxDownloadSize)
			throw new ModuleException('mediapool', 'Filesize: '.$this->calculateToMegaByte($size).' MB exceeds max widget size.');
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	public function checkFileAfterUpload(string $filePath): void
	{
		if (!$this->filesystem->fileExists($filePath))
			throw new ModuleException('mediapool', 'After Upload Check: '.$filePath.' not exists.');

		$this->fileSize = $this->filesystem->fileSize($filePath);
		if ($this->fileSize > $this->maxDownloadSize)
			throw new ModuleException('mediapool', 'After Upload Check: '.$this->calculateToMegaByte($this->fileSize).' MB exceeds max widget size.');
	}

	/**
	 * @throws FilesystemException
	 * @throws ModuleException
	 * @throws FrameworkException
	 * @throws ImagickException
	 */
	public function createThumbnail(string $filePath): void
	{
		$zipFilesystem = $this->zipFilesystemFactory->create($this->getAbsolutePath($filePath));
		if ($zipFilesystem->fileExists('config.xml'))
		{
			$this->configData = $zipFilesystem->read('config.xml');
			$this->configXML->load($this->configData)->parseBasic();
		}

		$fileInfo  = pathinfo($filePath);
		if ($zipFilesystem->fileExists($this->configXML->getIcon()))
		{
			$imageContent = $zipFilesystem->read($this->configXML->getIcon());
			$this->imagick->readImageBlob($imageContent);
			$this->imagick->thumbnailImage($this->thumbWidth, $this->thumbHeight, true);

			$this->thumbExtension = pathinfo($this->configXML->getIcon(), PATHINFO_EXTENSION);
			$thumbPath            = $this->config->getPaths('systemDir').'/'.$this->thumbPath.'/'.$fileInfo['filename']. '.'.$this->thumbExtension;
			$this->imagick->writeImage($thumbPath);
		}
		else
		{
			// we will show a standard thumbnail for the widget in
			$thumbPath = '/'.$this->thumbPath.'/'.$fileInfo['filename'].'.svg';
			$iconPath  = '/'.$this->iconsPath.'/widget.svg';
			$this->thumbExtension = 'svg';

			$this->filesystem->copy($iconPath, $thumbPath);
		}
	}
}