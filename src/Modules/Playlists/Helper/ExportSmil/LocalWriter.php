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

namespace App\Modules\Playlists\Helper\ExportSmil;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * Manages the creation and export of SMIL files for a specific playlist.
 * Provides functionality to initialize export and write SMIL files.
 */
class LocalWriter
{
	private readonly Config $config;
	private readonly Filesystem $fileSystem;
	private int $playlistId;
	private string $playlistBasePath;
//	private int $aclFile = 0664;
//	private int $aclDir  = 0775;

	/**
	 * @throws CoreException
	 */
	public function  __construct(Config $config, Filesystem $filesystem)
	{
		$this->fileSystem = $filesystem;
		$this->config = $config;
		$this->playlistBasePath = $this->config->getConfigValue('path_playlists', 'playlists');
	}

	public function initExport(int $playlistId): void
	{
		$this->playlistId = $playlistId;
	}

	/**
	 * @throws FilesystemException
	 */
	public function writeSMILFiles(PlaylistContent $playlistContent): void
	{
		$playlistPath = $this->playlistBasePath.'/'.$this->playlistId;
		$this->fileSystem->createDirectory($playlistPath);
		$this->fileSystem->write($playlistPath.'/prefetch.smil', $playlistContent->getContentPrefetch());
		$this->fileSystem->write($playlistPath.'/items.smil', $playlistContent->getContentElements());
		$this->fileSystem->write($playlistPath.'/exclusive.smil', $playlistContent->getContentExclusive());

	}

}