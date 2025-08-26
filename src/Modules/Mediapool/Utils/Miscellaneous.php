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
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * Class Miscellaneous
 *
 * Handles miscellaneous media-related operations, such as file upload checks
 * and thumbnail creation, while enforcing file size constraints.
 */
class Miscellaneous extends AbstractMediaHandler
{
	private int $maxSize;

	/**
	 * @throws CoreException
	 */
	public function __construct(Config $config, Filesystem $fileSystem)
	{
		parent::__construct($config, $fileSystem); // should be first

		$this->maxSize = (int) $this->config->getConfigValue('downloads', 'mediapool', 'max_file_sizes');
	}

	/**
	 * @throws ModuleException
	 */
	public function checkFileBeforeUpload(int $size): void
	{
		if ($size > $this->maxSize)
			throw new ModuleException('mediapool', 'Filesize: '.$this->calculateToMegaByte($size).' MB exceeds max size.');
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
		if ($this->fileSize > $this->maxSize)
			throw new ModuleException('mediapool', 'After Upload Check: '.$this->calculateToMegaByte($this->fileSize).' MB exceeds max size.');
	}

	/**
	 * @throws FilesystemException
	 */
	public function createThumbnail(string $filePath): void
	{
		$fileInfo  = pathinfo($filePath);

		$thumbPath = '/'.$this->thumbPath.'/'.$fileInfo['filename'].'.svg';

		if (array_key_exists('extension', $fileInfo) && ($fileInfo['extension'] === 'csv' || $fileInfo['extension'] === 'json' || $fileInfo['extension'] === 'xml'))
			$iconPath  = '/'.$this->iconsPath.'/database.svg';
		else
			$iconPath  = '/'.$this->iconsPath.'/file.svg';

		$this->thumbExtension = 'svg';

		$this->filesystem->copy($iconPath, $thumbPath);
	}
}