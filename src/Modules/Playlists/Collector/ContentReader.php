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

namespace App\Modules\Playlists\Collector;

// Todo create a base Class ContentHelper

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Modules\Playlists\Collector\Contracts\ContentReaderInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * Provides functionality to manage and read playlist content from the filesystem.
 *
 * This class implements methods to load various playlist-related files based on
 * a specified playlist ID. The paths to the files are configured during construction.
 */
class ContentReader implements ContentReaderInterface
{
	private string $playlistsPath;
	private readonly Filesystem $fileSystem;
	private int $playlistId;

	/**
	 * @throws CoreException
	 */
	public function __construct(Config $Config, Filesystem $fileSystem)
	{
		$this->playlistsPath = $Config->getConfigValue('path_playlists', 'playlists');
		$this->fileSystem = $fileSystem;
	}

	public function init(int $playlistId): static
	{
		$this->playlistId = $playlistId;

		return $this;
	}

	/**
	 * @throws FilesystemException
	 */
	public function loadPlaylistItems(): string
	{
		return $this->load('items.smil');
	}

	/**
	 * @throws FilesystemException
	 */
	public function loadPlaylistPrefetch(): string
	{
		return $this->load('prefetch.smil');
	}

	/**
	 * @throws FilesystemException
	 */
	public function loadPlaylistExclusive(): string
	{
		return $this->load('exclusive.smil');
	}

	/**
	 * @throws FilesystemException
	 */
	private function load(string $file): string
	{
		if ($this->playlistId == 0)
			return '';

		return $this->fileSystem->read($this->playlistsPath.'/'.$this->playlistId . '/'.$file);
	}
}