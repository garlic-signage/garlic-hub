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
use App\Framework\Media\Ffmpeg;
use App\Framework\Utils\Widget\ConfigXML;
use League\Flysystem\Filesystem;

/**
 * Factory class responsible for creating media handlers based on the provided MIME type.
 */
class MediaHandlerFactory
{
	private Config $config;
	private Filesystem $fileSystem;
	private ZipFilesystemFactory $zipFilesystemFactory;
	private ImagickFactory $imagickFactory;
	private Ffmpeg $ffmpeg;

	public function __construct(Config $config, Filesystem $fileSystem,	ZipFilesystemFactory $zipFilesystemFactory, ImagickFactory $imagickFactory, Ffmpeg $ffmpeg)
	{
		$this->config               = $config;
		$this->fileSystem           = $fileSystem;
		$this->zipFilesystemFactory = $zipFilesystemFactory;
		$this->imagickFactory       = $imagickFactory;
		$this->ffmpeg               = $ffmpeg;
	}

	/**
	 * @throws CoreException
	 */
	public function createHandler(string $mimeType): AbstractMediaHandler
	{
		return match (true)
		{
			str_starts_with($mimeType, 'image/') => new Image($this->config, $this->fileSystem, $this->imagickFactory->createImagick()),
			str_starts_with($mimeType, 'audio/') => new Audio($this->config, $this->fileSystem, $this->ffmpeg),
			str_starts_with($mimeType, 'video/') => new Video($this->config, $this->fileSystem, $this->ffmpeg, $this->imagickFactory->createImagick()),
			$mimeType === 'application/pdf' =>  new Pdf($this->config, $this->fileSystem, $this->imagickFactory->createImagick()),
			$mimeType === 'application/widget' || $mimeType === 'application/octet-stream' =>  new Widget($this->config,
				$this->fileSystem,
				$this->zipFilesystemFactory,
				$this->imagickFactory->createImagick(),
				new ConfigXML()
			),
			$mimeType === 'application/zip' || $mimeType === 'application/json', $mimeType === 'text/csv' ||
				$mimeType === 'application/xml' || $mimeType === 'application/rss+xml' || $mimeType === 'application/atom+xml' ||
				$mimeType === 'application/vnd.android.package-archive' ||
				$mimeType === 'application/smil', $mimeType === 'text/xml'
				=> new Miscellaneous($this->config, $this->fileSystem),

			default => throw new CoreException('Unsupported mime type: ' . $mimeType)
		};

	}
}