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
use App\Framework\Exceptions\ModuleException;
use Imagick;
use ImagickException;
use ImagickPixel;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class Pdf extends the AbstractMediaHandler and provides functionality to handle PDF files.
 * Includes methods for constructing the PDF handler, validating file size before and after upload,
 * and creating a thumbnail for a given PDF file.
 */
class Pdf extends AbstractMediaHandler
{
	private int $maxDocumentSize;
	private Imagick $imagick;

	/**
	 * @throws CoreException
	 */
	public function __construct(Config $config, Filesystem $fileSystem, Imagick $imagick)
	{
		parent::__construct($config, $fileSystem); // should be first

		$this->imagick = $imagick;
		$this->maxDocumentSize = (int) $this->config->getConfigValue('documents', 'mediapool', 'max_file_sizes');
	}

	/**
	 * @throws ModuleException
	 */
	public function checkFileBeforeUpload(int $size): void
	{
		if ($size > $this->maxDocumentSize)
			throw new ModuleException('mediapool', 'Filesize: '.$this->calculateToMegaByte($size).' MB exceeds max document size.');
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
		if ($this->fileSize > $this->maxDocumentSize)
			throw new ModuleException('mediapool', 'After Upload Check: '.$this->calculateToMegaByte($this->fileSize).' MB exceeds max document size.');

	}

	/**
	 * @throws ImagickException
	 */
	public function createThumbnail(string $filePath): void
	{
		$this->imagick->setResolution(150, 150); // DPI
		$this->imagick->readImage($this->getAbsolutePath($filePath) . '[0]');

		// some pdf will show a black image if the alpha channel is not removed
		$this->imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
		//$this->imagick->setImageBackgroundColor('white'); // probably needed let's test

		$this->imagick->setImageFormat('jpg');
		$this->imagick->thumbnailImage($this->thumbWidth, $this->thumbHeight, true);
		$fileInfo = pathinfo($filePath);
		$thumbPath = $this->config->getPaths('systemDir').'/'.$this->thumbPath.'/'.$fileInfo['filename']. '.jpg';
		$this->imagick->writeImage($thumbPath);

	}
}