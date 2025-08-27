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

namespace App\Modules\Playlists\Helper\ExportSmil\Utils;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;

/**
 * A class to manage and represent properties including fit, media alignment, and volume.
 */
class Properties
{
	private Config $config;
	private string $fit;
	private string $mediaAlign;
	private string $volume;
	/** @var string[] */
	private array $mediaAligns = ['topLeft', 'topMid', 'topRight', 'midLeft', 'center', 'midRight', 'bottomLeft', 'bottomMid', 'bottomRight'];
	/** @var string[] */
	private array $fits = ['fill', 'meet', 'meetBest', 'slice', 'scroll'];

	/**
	 * @param array<string,mixed> $properties
	 * @throws CoreException
	 */
	public function __construct(Config $config, array $properties)
	{
		$this->config     = $config;
		$this->fit        = $properties['fit'] ?? $this->config->getConfigValue('fit', 'playlists', 'Defaults');
		$this->mediaAlign = $properties['media_align'] ?? $this->config->getConfigValue('media_align', 'playlists', 'Defaults');
		$this->volume     = $properties['volume'] ?? $this->config->getConfigValue('volume', 'playlists', 'Defaults');
	}

	public function getFit(): string
	{
		if (in_array($this->fit, $this->fits))
			return 'fit="'.$this->fit.'" ';

		return '';
	}

	public function getMediaAlign(): string
	{
		if (in_array($this->mediaAlign, $this->mediaAligns))
			return 'mediaAlign="'.$this->mediaAlign.'" ';

		return '';
	}

	public function getVolume(): string
	{
		return 'soundLevel="'.$this->volume.'" ';
	}

}