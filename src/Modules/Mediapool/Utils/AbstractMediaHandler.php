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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * AbstractMediaHandler class serves as a base handler for managing media file operations.
 * It includes abstract methods for verifying and processing files, and concrete methods
 * for handling file uploads, metadata, dimensions, and storage utilities.
 *
 * This class is designed to work with media files, including their configuration,
 * filesystem operations, and auxiliary properties like dimensions and metadata.
 */
abstract class AbstractMediaHandler
{
	protected Config $config;
	protected Filesystem $filesystem;
	protected int $thumbWidth;
	protected int $thumbHeight;
	protected int $maxWidth;
	protected int $maxHeight;
	protected string $thumbPath;
	protected string $uploadPath;
	protected string $originalPath;
	protected string $iconsPath;
	protected string $previewPath;
	/** @var array<string,mixed>  */
	protected array $dimensions = [];
	protected int $fileSize = 0;
	protected string $thumbExtension = 'jpg';
	protected float $duration = 0.0;
	protected string $configData = '';
	/** @var array<string,mixed>  */
	protected array $metadata = [];

	/**
	 * @param Config $config
	 * @param Filesystem $filesystem
	 * @throws CoreException
	 */
	public function __construct(Config $config, Filesystem $filesystem)
	{
		$this->config     = $config;
		$this->filesystem = $filesystem;

		$this->maxWidth   = (int) $this->config->getConfigValue('width', 'mediapool', 'max_resolution');
		$this->maxHeight  = (int) $this->config->getConfigValue('height', 'mediapool', 'max_resolution');

		$this->thumbWidth   = (int) $this->config->getConfigValue('thumb_width', 'mediapool', 'dimensions');
		$this->thumbHeight  = (int) $this->config->getConfigValue('thumb_height', 'mediapool', 'dimensions');
		$this->uploadPath   = $this->config->getConfigValue('uploads', 'mediapool', 'directories');
		$this->thumbPath    = $this->config->getConfigValue('thumbnails', 'mediapool', 'directories');
		$this->originalPath = $this->config->getConfigValue('originals', 'mediapool', 'directories');
		$this->previewPath  = $this->config->getConfigValue('previews', 'mediapool', 'directories');
		$this->iconsPath    = $this->config->getConfigValue('icons', 'mediapool', 'directories');
	}

	/**
	 * @param array<string,mixed> $metadata
	 */
	public function setMetadata(array $metadata): void
	{
		$this->metadata = $metadata;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getMetadata(): array
	{
		return $this->metadata;
	}

	public function getConfigData(): string
	{
		return $this->configData;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getDimensions(): array
	{
		return $this->dimensions;
	}

	public function getDuration(): float
	{
		return $this->duration;
	}

	public function getThumbExtension(): string
	{
		return $this->thumbExtension;
	}

	public function getFileSize(): int
	{
		return $this->fileSize;
	}

	/**
	 * @throws FilesystemException
	 */
	public function exists(string $filePath): bool
	{
		return $this->filesystem->fileExists($filePath);
	}

	abstract public function checkFileBeforeUpload(int $size): void;
	abstract public function checkFileAfterUpload(string $filePath): void;
	abstract public function createThumbnail(string $filePath): void;

	public function uploadFromLocal(UploadedFileInterface $uploadedFile): string
	{
		$targetPath = strtolower('/'. $this->originalPath .'/'. $uploadedFile->getClientFilename());
		$uploadedFile->moveTo($this->getAbsolutePath($targetPath));

		return $targetPath;
	}

	/**
	 * @throws GuzzleException
	 */
	public function uploadFromExternal(Client $client, string $fileURI): string
	{
		/** @var array{path: string} $parsedUrl */
		$parsedUrl  = parse_url($fileURI);

		/** @var array{basename: string} $pathInfo */
		$pathInfo   = pathinfo($parsedUrl['path']);
		$targetPath = strtolower('/'. $this->originalPath .'/'. $pathInfo['basename']);

		$client->request('GET', $fileURI, ['sink' => $this->getAbsolutePath($targetPath)]);
		return $targetPath;
	}


	/**
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	public function determineNewFilename(string $filePath): string
	{
		if (!$this->filesystem->fileExists($filePath))
			throw new ModuleException('mediapool', 'Filesize: '.$filePath.' not exists');

		$stream = $this->filesystem->readStream($filePath);

		$contents = stream_get_contents($stream);
		if ($contents === false)
			throw new ModuleException('mediapool', 'Stream from '.$filePath.' not readable');

		$hash = hash('sha256', $contents);
		fclose($stream);

		return $hash;
	}

	public function determineNewFilePath(string $oldFilePath, string $filehash, string $ext): string
	{
		/** @var array{dirname:string} $fileInfo */
		$fileInfo    = pathinfo($oldFilePath);
		return $fileInfo['dirname']. '/'.$filehash.'.'.$ext;
	}

	/**
	 * @throws FilesystemException
	 */
	public function removeUploadedFile(string $filePath): void
	{
		$this->filesystem->delete($filePath);
	}

	/**
	 * @throws FilesystemException
	 */
	public function rename(string $oldFilePath, string $newFilePath): void
	{
		$this->filesystem->move($oldFilePath, $newFilePath);
	}

	public function getAbsolutePath(string $filePath): string
	{
		return $this->config->getPaths('systemDir') . $filePath;
	}

	protected function calculateToMegaByte(int $bytes): float
	{
		return round($bytes / (1024 ** 2), 2);
	}

}