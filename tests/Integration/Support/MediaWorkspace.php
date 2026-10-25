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

namespace Tests\Integration\Support;

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\System\ShellExecutor;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * A storage of the mediapool in a temporary directory: the directories of the configuration, the icons
 * and a real file system. The media handlers work on it like on the storage of the application.
 */
final class MediaWorkspace
{
	public readonly string $dir;
	public readonly Config $config;
	public readonly Filesystem $filesystem;

	public function __construct()
	{
		$root      = (string) realpath(__DIR__ . '/../../..');
		$this->dir = sys_get_temp_dir() . '/garlic-hub-media-' . bin2hex(random_bytes(4));
		foreach (['uploads', 'thumbs', 'originals', 'previews'] as $sub)
			mkdir($this->dir . '/var/storage/mediapool/' . $sub, 0775, true);
		mkdir($this->dir . '/public/images/icons', 0775, true);
		mkdir($this->dir . '/incoming', 0775, true);
		foreach ((array) glob($root . '/public/images/icons/*.svg') as $icon)
			copy((string) $icon, $this->dir . '/public/images/icons/' . basename((string) $icon));

		$this->config     = new Config(new IniConfigLoader($root . '/config/settings'), ['systemDir' => $this->dir], ['APP_PLATFORM_EDITION' => 'edge']);
		$this->filesystem = new Filesystem(new LocalFilesystemAdapter($this->dir));
	}

	/**
	 * The handlers of the media, working in this storage.
	 */
	public function handlerFactory(): MediaHandlerFactory
	{
		return new MediaHandlerFactory(
			$this->config,
			$this->filesystem,
			new ZipFilesystemFactory(),
			new ImagickFactory(),
			new Ffmpeg($this->config, $this->filesystem, new MediaProperties(), new ShellExecutor())
		);
	}

	/**
	 * The place of a file in the storage as the handlers name it: with a slash at the start, relative to the system directory.
	 */
	public function original(string $name): string
	{
		return '/var/storage/mediapool/originals/' . $name;
	}

	public function absolute(string $path): string
	{
		return $this->dir . $path;
	}

	/**
	 * Puts a file into the storage of the originals.
	 */
	public function store(string $name, string $content): string
	{
		file_put_contents($this->absolute($this->original($name)), $content);

		return $this->original($name);
	}

	/**
	 * A file of the given size which takes no room on the disk. Only the size counts for the handlers.
	 */
	public function sparse(string $name, int $size): string
	{
		$file = fopen($this->absolute($this->original($name)), 'w');
		if ($file === false)
			throw new \RuntimeException('The file can not be created.');
		ftruncate($file, max(0, $size));
		fclose($file);

		return $this->original($name);
	}

	/**
	 * @return list<string> the names of the files in a directory of the storage
	 */
	public function listing(string $sub): array
	{
		$names = array_values(array_filter((array) scandir($this->dir . '/var/storage/mediapool/' . $sub), static fn(mixed $name): bool => is_string($name) && !in_array($name, ['.', '..'], true)));
		sort($names);

		return $names;
	}

	public function remove(): void
	{
		$this->removeDirectory($this->dir);
	}

	private function removeDirectory(string $dir): void
	{
		if (!is_dir($dir))
			return;

		foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry)
			is_dir($dir . '/' . $entry) && !is_link($dir . '/' . $entry) ? $this->removeDirectory($dir . '/' . $entry) : unlink($dir . '/' . $entry);
		rmdir($dir);
	}
}
