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

namespace App\Framework\Media;


use App\Framework\Core\Config\Config;
use App\Framework\Core\ShellExecutor;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use stdClass;

/**
 * some convenient methods wrapping around ffmpeg functions
 *
 * The constructor only sets needed objects, but does not do anything
 *
 * Fore initialisation use the Ffmpeg::init($file_name) method.
 *
 * Ffmpeg::init() will take the video file name as an argument,
 * determine if it is local or on a remote server and call ffprobe (Ffmpeg::probeFile()) to read
 * metadata of the video and sets these data to the internal property Ffmpeg::media_properties = array()
 */
class Ffmpeg
{

	private readonly Config $config;
	private readonly Filesystem $filesystem;
	private readonly ShellExecutor $shellExecutor;

	private string $probePath = '/usr/bin/ffprobe';
	private string $executablePath = '/usr/bin/ffmpeg';
	private string $scaleOptions = '';
	// @phpstan-ignore-next-line // use later
	private string $audioQuality = '';
	// @phpstan-ignore-next-line // use later
	private string $destinationFile = '';
	// @phpstan-ignore-next-line // use later
	private string $options = '';

	private MediaProperties $mediaProperties;
	/** @var array<string,mixed>  */
	private array $metadata = [];

	public function __construct(Config $config, Filesystem $filesystem, MediaProperties $mediaProperties, ShellExecutor $shellExecutor)
	{
		$this->config          = $config;
		$this->filesystem      = $filesystem;
		$this->shellExecutor   = $shellExecutor;
		$this->mediaProperties = $mediaProperties;
	}

	/**
	 * @param array<string,mixed> $metadata
	 */
	public function setMetadata(array $metadata): static
	{
		$this->metadata = $metadata;
		return $this;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getMetadata(): array
	{
		return $this->metadata;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getMediaProperties(): array
	{
		return $this->mediaProperties->toArray();
	}

	public function getDuration(): float
	{
		return $this->mediaProperties->getDuration();
	}

	/**
	 * Initialize FFmpeg for processing a media file.
	 * Cleans up all internal properties for re-using with other videos.
	 *
	 * @param string $filePath Path to the media file
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	public function init(string $filePath): self
	{
		$this->configureBinaryPaths();
		$this->resetProperties();

		if (!$this->filesystem->fileExists($filePath))
			throw new FrameworkException('File does not exist: ' . $filePath);

		$this->probeFile($this->getAbsolutePath($filePath));
		return $this;
	}

	/**
	 * Create a video thumbnail at a specific timestamp
	 *
	 * @param string $destination Destination directory path
	 * @return string Path to the created thumbnail
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws FilesystemException
	 */
	public function createVideoThumbnail(string $destination): string
	{
		if (!$this->mediaProperties->hasVideoStream())
		{
			throw new FrameworkException(
				'Cannot create video thumbnail for ' . $this->mediaProperties->getFilename() .
				'. File has no readable video stream'
			);
		}

		$command = sprintf(
			'%s -i %s %s -ss 00:00:02 -an -r 1 -vframes 1 -y %s/vid_%%d.jpg 2>&1',
			$this->executablePath,
			$this->mediaProperties->getFilename(),
			$this->scaleOptions,
			$this->getAbsolutePath($destination)
		);

		$this->shellExecutor->setCommand($command);
		$this->shellExecutor->execute();

		$thumbnailPath = $destination . '/vid_1.jpg';
		if (!$this->filesystem->fileExists($thumbnailPath)) {
			throw new FrameworkException('Thumbnail ' . $thumbnailPath . ' not found');
		}

		return $thumbnailPath;
	}

	/**
	 * Configure binary paths from configuration
	 *
	 * @throws CoreException
	 */
	private function configureBinaryPaths(): void
	{
		$this->executablePath = $this->config->getConfigValue('path_to_ffmpeg_bin', 'video');
		$this->probePath      = $this->config->getConfigValue('path_to_ffmpeg_probe', 'video');
		$this->audioQuality   = $this->config->getConfigValue('audio_quality_ffmpeg_param', 'video');

	}

	/**
	 * Reset processing properties
	 */
	private function resetProperties(): void
	{
		$this->mediaProperties->reset();
		$this->options = '';
		$this->scaleOptions = '';
		$this->destinationFile = '';
	}

	/**
	 * Probe media file to get its properties
	 *
	 * @param string $filePath Absolute path to the file
	 * @throws FrameworkException
	 * @throws CoreException
	 */
	private function probeFile(string $filePath): void
	{
		$command = sprintf(
			'%s -v quiet -print_format json -show_format -show_streams %s',
			$this->probePath,
			$filePath
		);

		$this->shellExecutor->setCommand($command);
		$result = $this->shellExecutor->executeSimple();

		$metadata = json_decode($result);
		if (!($metadata instanceof stdClass) || !isset($metadata->format))
			throw new FrameworkException('Probing media file failed. Unsupported file type for file ' . $filePath . '. Using command: ' . $command);

		$this->mediaProperties->fromStdClass($metadata, $this->metadata);
	}

	private function getAbsolutePath(string $filePath): string
	{
		return $this->config->getPaths('systemDir') . $filePath;
	}

}