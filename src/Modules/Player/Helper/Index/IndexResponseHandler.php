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

namespace App\Modules\Player\Helper\Index;

use App\Framework\Exceptions\ModuleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class responsible for handling HTTP responses for index files.
 * This class manages the caching mechanisms including ETags and Last-Modified headers,
 * and ensures appropriate HTTP response codes such as 200 (OK) or 304 (Not Modified).
 *
 * Strategy:
 * There are two HTTP variables player can send:
 * 1. If-None-Match with a previous sent etag
 * 2. If-Modified-Since with the timestamp of the current file
 *
 * We prioritize If-None-Match. If is sent and the match is equal the script responds with 304 (nothing changed) even
 * when If-Modified-Since differ
 *
 * If If-None-Match then we will look at If-Modified-Since
 *
 * Server will always (HEAD and GET) responds with Cache-Control, etag, and Last-Modified.
 * This is to ensure that the client can also decide if he wants to set a GET-Request or not.
 *
 * etag for index.smil is always the md5-hash of the content.
 *
 */
class IndexResponseHandler
{
	private readonly FileUtils $fileUtils;
	 private int $fileMTime;
	 private string $etag;
	 private string $lastModified;
	 private string $cacheControl;
	 private string $filePath;
	 private ?string $clientHasNoneMatch;
	 private ?string $clientHasModifiedSince;

	/**
	 * @param FileUtils $fileUtils
	 */
	public function __construct(FileUtils $fileUtils)
	{
		$this->fileUtils = $fileUtils;
	}

	/**
	 * @param array<string,mixed> $server
	 * @throws ModuleException
	 */
	public function init(array $server, string $filePath): void
	{
		$this->filePath               = $filePath;
		$this->clientHasNoneMatch     = $server['HTTP_IF_NONE_MATCH'] ?? $server['If-None-Match'] ?? null;
		$this->clientHasModifiedSince = $server['HTTP_IF_MODIFIED_SINCE'] ?? $server['If-Modified-Since'] ?? null;

		$this->fileMTime    = $this->fileUtils->getFileMTime($filePath);
		$this->etag         = $this->fileUtils->getETag($filePath);
		$this->lastModified = gmdate('D, d M Y H:i:s', $this->fileMTime) . ' GMT';
		$this->cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';
	}

/*
	 only for debugging and should be replaced by some better concept
	public function debugServer(): void
	{
		$logFilePath = '../var/logs/server-variable.log';
		$formattedHeaders = print_r($_SERVER, true);
		$formattedHeaders .= "\n---------------------------------------------------\n";
		file_put_contents($logFilePath, $formattedHeaders, FILE_APPEND);
	}


	public function debugHeader(array $headers): void
	{
		$logFilePath = '../var/logs/client-headers.log';
		$formattedHeaders = '';
		foreach ($headers as $name => $values)
		{
			$formattedHeaders .= $name . ': ' . implode(', ', $values) . PHP_EOL;
		}
		file_put_contents($logFilePath, $formattedHeaders, FILE_APPEND);
	}
*/
	/**
	 * if player sends an If-None-Match with etag then check it and response with
	 * 200 and a new etag
	 *
	 * If etag matches send 304 (not modified)
	 */
	public function doHEAD(ResponseInterface $response): ResponseInterface
	{
		if ($this->shouldSend304())
			return $this->return304($response);

		$response = $this->addAccessControlHeader($response);
		return $response
			->withHeader('Cache-Control', $this->cacheControl)
			->withHeader('Content-Type', 'application/smil+xml')
			->withHeader('etag', $this->etag)
			->withHeader('Last-Modified', $this->lastModified)
			->withStatus(200);
	}

	/**
	 * If a player do not send any HEAD requests we need to secure that
	 * he will get an index for sure.
	 *
	 * A full index send will happen when:
	 *  1. there is no If-Modified-Since or one with older datetime
	 *  2. there is no If-None-Match sent or is different from current etag
	 *
	 * /+
	 * otherwise 304 status will be sent
	 * @throws ModuleException
	 */
	public function doGET(ResponseInterface $response): ResponseInterface
	{
		if ($this->shouldSend304())
			return $this->return304($response);

		// not cached or cache outdated, 200 OK send index.smil
		$fileStream = $this->fileUtils->createStream($this->filePath);
		$response   = $this->addAccessControlHeader($response);
		return $response
			->withBody($fileStream)
			->withHeader('Cache-Control', $this->cacheControl)
			->withHeader('etag', $this->etag)
			->withHeader('Last-Modified', $this->lastModified)
			->withHeader('Content-Length', (string) $this->fileUtils->getFileSize($this->filePath)) // will not work with php-fpm or nginx
			->withHeader('Content-Type', 'application/smil+xml')
			->withHeader('Content-Description', 'File Transfer')
			->withHeader('Content-Disposition', 'attachment; filename="' . basename($this->filePath) . '"')
			->withStatus(200);
	}

	private function return304(ResponseInterface $response): ResponseInterface
	{
		$response = $this->addAccessControlHeader($response);
		return $response
			->withHeader('Cache-Control', $this->cacheControl)
			->withHeader('etag', $this->etag)
			->withHeader('Last-Modified', $this->lastModified)
			->withStatus(304);
	}

	private function shouldSend304(): bool
	{
		if ($this->clientHasNoneMatch !== null)
			return $this->clientHasNoneMatch === $this->etag;

		if ($this->clientHasModifiedSince !== null)
			// the client has the file when its date is the date of the file or newer
			return strtotime($this->clientHasModifiedSince) >= $this->fileMTime;

		return false; // if both are not present in request return always a 200.
	}

	private function addAccessControlHeader(ResponseInterface $response): ResponseInterface
	{
		// the answer depends on who asks (the uuid is in the agent): a cache between must not give it to another player
		return $response->withHeader('Vary', 'User-Agent, X-Signage-Agent')
						->withHeader('Access-Control-Allow-Origin', '*')
						->withHeader('Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS')
						->withHeader('Access-Control-Max-Age', '86400')
						->withHeader('Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent');
	}
}