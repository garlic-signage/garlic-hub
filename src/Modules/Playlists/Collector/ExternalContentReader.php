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


use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Collector\Contracts\ExternalContentReaderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ExternalContentReader
 *
 * This class is responsible for reading external content, managing cached files, and ensuring the content is updated when necessary.
 * It uses a FileSystem instance for file operations, a Client instance for HTTP requests,
 * and manages cached data related to a playlist represented by a remote URL.
 */
class ExternalContentReader implements ExternalContentReaderInterface
{
	private string $cachePath;
	private FileSystem $fileSystem;
	private Client $client;
	private string $cachedFile = '';
	private ResponseInterface $response;
	private string $playlistLink;

	public function __construct(FileSystem $fileSystem, Client $client, string $cachePath)
	{
		$this->cachePath  = $cachePath;
		$this->client     = $client;
		$this->fileSystem = $fileSystem;
	}

	/**
	 * @throws GuzzleException
	 */
	public function init(string $url): static
	{
		$this->playlistLink = $url;
		$this->cachedFile   = $this->cachePath . '/' . md5($this->playlistLink) . '.smil';
		$this->response     = $this->client->head($this->playlistLink);

		return $this;
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 * @throws GuzzleException
	 */
	public function loadPlaylistItems(): string
	{
		if (!$this->fileSystem->fileExists($this->cachedFile))
			$this->handleFirstDownload();
		else
			$this->checkForRemoteUpdated();

		return $this->fileSystem->read($this->cachedFile);
	}

	/**
	 * @throws GuzzleException
	 * @throws FilesystemException
	 */
	private function checkForRemoteUpdated(): void
	{
		if ($this->response->getStatusCode() === 200 && $this->mustUpdate())
			$this->downloadRemoteFile();
	}

	/**
	 * @throws FilesystemException
	 */
	private function mustUpdate(): bool
	{
		$lastModified  = strtotime($this->response->getHeaderLine('Last-Modified'));
		$contentLength = (int) $this->response->getHeaderLine('Content-Length');

		return $lastModified < $this->fileSystem->lastModified($this->cachedFile) || $contentLength != $this->fileSystem->fileSize($this->cachedFile);
	}

	/**
	 * @throws ModuleException
	 * @throws GuzzleException
	 */
	private function handleFirstDownload(): void
	{
		if ($this->response->getStatusCode() != 200)
			throw new ModuleException('smil_index', 'Http-Code of '.$this->playlistLink.' is: '.$this->response->getStatusCode());

		$this->downloadRemoteFile();
	}

	/**
	 * @throws GuzzleException
	 */
	private function downloadRemoteFile(): void
	{
		$this->client->get($this->playlistLink, ['sink' => $this->cachedFile]);
	}

}