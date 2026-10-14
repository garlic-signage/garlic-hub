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

use App\Framework\Core\Config\Config;
use App\Framework\Media\MediaUrlSigner;

class BaseTemplateOrchestrator
{
	protected string $baseUrl;

	public function __construct(protected readonly Config $config, protected readonly MediaUrlSigner $mediaUrlSigner)
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

	protected function restoreSrc(array &$objects): void
	{
		foreach ($objects as &$obj)
		{
			if (isset($obj['fileName']) && is_string($obj['fileName']))
				$obj['src'] = $this->buildMediaUrl($obj['fileName']);

			if (isset($obj['objects']) && is_array($obj['objects']))
				$this->restoreSrc($obj['objects']);
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

	protected function removeSrc(array &$objects): void
	{
		foreach ($objects as &$obj)
		{
			unset($obj['src']);
			if (isset($obj['objects']) && is_array($obj['objects']))
				$this->removeSrc($obj['objects']);
		}
	}

	protected function validate(string $content): string
	{
		// Scheme validation is not necessary
		$JSON = json_decode($content, true);
		if (!is_array($JSON) || !isset($JSON['objects']) || !is_array($JSON['objects']))
			return '';

		if (count($JSON['objects']) > 1000)
			return '';

		// reove Src for security reasons XSS etc
		$this->removeSrc($JSON['objects']);

		return json_encode($JSON);
	}

}