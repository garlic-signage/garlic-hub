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

use App\Framework\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

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
		$this->iconsPath    = $this->config->getConfigValue('icons', 'mediapool', 'directories');
	}

	public function setStoragePaths(string $moduleName): void
	{
		$this->thumbPath    = $this->config->getConfigValue('thumbnails', $moduleName, 'directories');
		$this->originalPath = $this->config->getConfigValue('originals', $moduleName, 'directories');
		$this->previewPath  = $this->config->getConfigValue('previews', $moduleName, 'directories');
	}

	public function getOriginalPath(): string
	{
		return $this->originalPath;
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

	/**
	 * The biggest file of this kind in bytes.
	 */
	abstract public function getMaxFileSize(): int;

	abstract public function checkFileBeforeUpload(int $size): void;
	abstract public function validateStoredFile(string $filePath): void;
	abstract public function createThumbnail(string $filePath): void;

	/**
	 * The file is put into a directory of its own below the upload directory, with the name of the client.
	 * Two uploads can never meet, and a name like the one of an original in the storage replaces nothing.
	 * The file gets its place in the storage with the name of its hash, see UploadService.
	 *
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	public function uploadFromLocal(UploadedFileInterface $uploadedFile): string
	{
		$name       = $this->cleanFilename((string) $uploadedFile->getClientFilename());
		if ($name === '')
			throw new ModuleException('mediapool', 'Invalid file name.');

		$targetPath = $this->createUploadTarget($name);
		try
		{
			$uploadedFile->moveTo($this->getAbsolutePath($targetPath));
		}
		catch (Throwable $e)
		{
			$this->removeUploadedFile($targetPath);
			throw $e;
		}

		return $targetPath;
	}

	/**
	 * Strips any directory part (also with backslashes) and control characters from a name which comes from
	 * the outside, so it can not leave the upload directory. Gives '' when nothing usable is left.
	 */
	private function cleanFilename(string $name): string
	{
		$name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);
		$name = basename(str_replace('\\', '/', $name));

		return ($name === '.' || $name === '..') ? '' : $name;
	}

	/**
	 * @throws FilesystemException
	 */
	private function createUploadTarget(string $name): string
	{
		$directory = '/' . $this->uploadPath . '/' . bin2hex(random_bytes(8));
		$this->filesystem->createDirectory($directory);

		return $directory . '/' . strtolower($name);
	}

	/**
	 * The download stops when it gets bigger than the handler allows, whatever the server said about the size.
	 *
	 * @param array<string,mixed> $curlOpts Guzzle request options
	 * @throws GuzzleException
	 * @throws FilesystemException
	 */
	public function uploadFromExternal(Client $client, string $fileURI, array $curlOpts): string
	{
		$name       = $this->cleanFilename((string) parse_url($fileURI, PHP_URL_PATH));
		$targetPath = $this->createUploadTarget($name !== '' ? $name : 'external');
		$maxSize    = $this->getMaxFileSize();
		$sink = null;
		try
		{
			$sink = new SizeLimitedStream(
				Utils::streamFor(Utils::tryFopen($this->getAbsolutePath($targetPath), 'w')),
				$maxSize,
				fn(int $size): TranslatableModuleException => $this->createFilesizeError($size, $maxSize)
			);
			$client->request('GET', $fileURI, array_merge($curlOpts, ['sink' => $sink]));
		}
		catch (Throwable $e)
		{
			$sink?->close();
			$this->removeUploadedFile($targetPath);
			// Guzzle wraps what a write throws: the message about the size is for the user
			throw $this->findFilesizeError($e) ?? $e;
		}
		$sink->close();

		return $targetPath;
	}

	private function findFilesizeError(Throwable $e): ?TranslatableModuleException
	{
		for ($previous = $e; $previous !== null; $previous = $previous->getPrevious())
		{
			if ($previous instanceof TranslatableModuleException)
				return $previous;
		}

		return null;
	}

	/**
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	public function determineNewFilename(string $filePath): string
	{
		if (!$this->filesystem->fileExists($filePath))
			throw new ModuleException('mediapool', 'Filesize: '.$filePath.' not exists');

		// read in pieces: a video of a gigabyte must not be loaded into the memory to get its hash
		$stream  = $this->filesystem->readStream($filePath);
		$context = hash_init('sha256');
		hash_update_stream($context, $stream);
		fclose($stream);
		$hash = hash_final($context);

		return $hash;
	}

	/**
	 * The place in the storage: the name is the hash, whatever the name of the upload was.
	 */
	public function determineNewFilePath(string $oldFilePath, string $filehash, string $ext): string
	{
		return '/' . $this->originalPath . '/' . $filehash . '.' . $ext;
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	public function writeBinaryString(string $filePath, string $binaryContent): void
	{
		if ($binaryContent === '')
			throw new ModuleException('mediapool', 'Decoded file is empty.');

		if ($this->filesystem->fileExists($filePath))
			$this->filesystem->delete($filePath);

		$this->filesystem->write($filePath, $binaryContent);
	}

	/**
	 * Deletes the file and the directory of its upload, when it has one.
	 *
	 * @throws FilesystemException
	 */
	public function removeUploadedFile(string $filePath): void
	{
		if ($this->filesystem->fileExists($filePath))
			$this->filesystem->delete($filePath);

		$directory = dirname($filePath);
		if (dirname($directory) === '/' . $this->uploadPath && $this->filesystem->directoryExists($directory))
			$this->filesystem->deleteDirectory($directory);
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

	protected function createFilesizeError(int $size, int $maxSize): TranslatableModuleException
	{
		return new TranslatableModuleException('mediapool', 'filesize_exceeded', [
			'size'     => (string) $this->calculateToMegaByte($size),
			'max_size' => (string) $this->calculateToMegaByte($maxSize)
		]);
	}

	/**
	 * @param 'width'|'height' $dimension
	 */
	protected function createResolutionError(string $dimension, int $value): TranslatableModuleException
	{
		return new TranslatableModuleException('mediapool', $dimension . '_exceeded', [
			'value' => $value,
			'max'   => $dimension === 'width' ? $this->maxWidth : $this->maxHeight
		]);
	}

	protected function calculateToMegaByte(int $bytes): float
	{
		return round($bytes / (1024 ** 2), 2);
	}

}