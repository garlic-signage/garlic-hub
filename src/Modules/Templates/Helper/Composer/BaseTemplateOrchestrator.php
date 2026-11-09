<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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


namespace App\Modules\Templates\Helper\Composer;

use App\Framework\Config\Config;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Mediapool\Services\MediaService;

class BaseTemplateOrchestrator
{
	/** the picture of an image placeholder of the bundled templates, from the hub itself, not the content server */
	public const string PLACEHOLDER_SRC = '/images/templates/placeholder.png';
	/** the result of a save which has an image the user may not use */
	public const int MEDIA_REFUSED = -1;

	protected string $baseUrl;

	public function __construct(protected readonly Config $config, protected readonly MediaUrlSigner $mediaUrlSigner, protected readonly MediaService $mediaService)
	{
		$url = $this->config->getConfigValue('url', 'mediapool', 'content_server');
		if ($url === '')
		{
			$isHttps = isset($_SERVER['HTTPS'])
				|| ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
				|| ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on';

			$url = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST']);
		}

		$this->baseUrl = rtrim($url, '/');
	}

	/**
	 * The content for the composer with fresh signed media urls, '' when it is no content of the composer.
	 */
	protected function restoreContent(string $content): string
	{
		$json = $this->decodeContent($content);
		if ($json === null)
			return '';

		$this->restoreSrc(self::objectsOf($json));

		return json_encode($json) ?: '';
	}

	/**
	 * The JSON objects stay objects: decoded as arrays, e.g. the styles of a text {"0": {"0": {...}}} (line and
	 * character of fabric.js 5) would come back as a list, which fabric.js reads as another format and breaks the text.
	 *
	 * @return \stdClass|null with the list objects
	 */
	protected function decodeContent(string $content): ?\stdClass
	{
		$json = json_decode($content);
		if (!$json instanceof \stdClass || !isset($json->objects) || !is_array($json->objects))
			return null;

		return $json;
	}

	/**
	 * @return array<mixed>
	 */
	private static function objectsOf(\stdClass $json): array
	{
		return isset($json->objects) && is_array($json->objects) ? $json->objects : [];
	}

	/**
	 * @param array<mixed> $objects
	 */
	protected function restoreSrc(array $objects): void
	{
		foreach ($objects as $obj)
		{
			if (!$obj instanceof \stdClass)
				continue;

			if (isset($obj->fileName) && is_string($obj->fileName))
				$obj->src = $this->buildMediaUrl($obj->fileName);
			elseif (($obj->placeholder ?? false) === true)
				$obj->src = self::PLACEHOLDER_SRC;

			if (isset($obj->objects) && is_array($obj->objects))
				$this->restoreSrc($obj->objects);
		}
	}

	/**
	 * Only the file name of the mediapool original is stored inside a template,
	 * the signed delivery url is rebuilt on every load as it expires.
	 */
	protected function buildMediaUrl(string $fileName): string
	{
		$checksum  = pathinfo($fileName, PATHINFO_FILENAME);
		$extension = pathinfo($fileName, PATHINFO_EXTENSION);
		if ($checksum === '' || $extension === '')
			return '';

		return $this->baseUrl . $this->mediaUrlSigner->originalUrl($checksum, $extension);
	}

	/**
	 * @param array<mixed> $objects
	 */
	protected function removeSrc(array $objects): void
	{
		foreach ($objects as $obj)
		{
			if (!$obj instanceof \stdClass)
				continue;

			unset($obj->src);
			if (isset($obj->objects) && is_array($obj->objects))
				$this->removeSrc($obj->objects);
		}
	}

	protected function validate(string $content): string
	{
		// Scheme validation is not necessary
		$json = $this->decodeContent($content);
		if ($json === null)
			return '';

		$objects = self::objectsOf($json);
		if (count($objects) > 1000)
			return '';

		// remove src for security reasons XSS etc.
		$this->removeSrc($objects);

		return json_encode($json) ?: '';
	}

	/**
	 * Loading a template signs a url for every file name in it, for everybody who uses the template. So a new file
	 * name must be one which the user may read: otherwise a known checksum would give him any file of the mediapool.
	 * The ones which were in the content before stay, an editor keeps the images of another one.
	 *
	 * @throws \App\Framework\Exceptions\CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \App\Framework\Exceptions\UserException
	 */
	protected function hasForeignFiles(string $newContent, string $oldContent): bool
	{
		$before = self::fileNamesOf($oldContent);
		foreach (self::fileNamesOf($newContent) as $fileName)
		{
			if (!in_array($fileName, $before, true) && !$this->mediaService->isReadableFile($fileName))
				return true;
		}

		return false;
	}

	/**
	 * @return list<string>
	 */
	private static function fileNamesOf(string $content): array
	{
		$json = json_decode($content);
		if (!$json instanceof \stdClass)
			return [];

		$names = [];
		self::collectFileNames(self::objectsOf($json), $names);

		return array_values(array_unique($names));
	}

	/**
	 * @param array<mixed> $objects
	 * @param list<string> $names
	 */
	private static function collectFileNames(array $objects, array &$names): void
	{
		foreach ($objects as $obj)
		{
			if (!$obj instanceof \stdClass)
				continue;

			if (isset($obj->fileName) && is_string($obj->fileName))
				$names[] = $obj->fileName;
			if (isset($obj->objects) && is_array($obj->objects))
				self::collectFileNames($obj->objects, $names);
		}
	}
}