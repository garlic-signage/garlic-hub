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

namespace App\Modules\Playlists\Helper\ExportSmil\items;

use App\Modules\Playlists\Helper\ItemDatasource;
use App\Modules\Playlists\Helper\ItemFlags;

/**
 * Abstract class representing a generic media object.
 * Provides methods for setting media links, generating prefetch tags,
 * and collecting media-related attributes and parameters.
 */
abstract class Media extends Base
{
	protected string $link = '';
	public function setLink(string $link):static
	{
		// ltrim required because the media path has a training slash
		$this->link = ltrim($link, '/');

		return $this;
	}

	public function getPrefetchTag(): string
	{
		$ret = '';
		if ($this->item['mimetype'] !== 'text/html') // to not set prefetch for websites
			$ret = self::TABSTOPS_TAG.'<prefetch src="'.$this->link.'" />'."\n";

		return $ret;
	}

	protected function collectMediaAttributes(): string
	{
		return $this->collectAttributes().
			'region="screen" src="'.$this->link.'" '. $this->determineDuration().
			$this->properties->getFit().
			$this->properties->getMediaAlign();
	}

	protected function collectParameters(): string
	{
		$param = '';
		if ($this->item['datasource'] === ItemDatasource::FILE->value)
			$param = self::TABSTOPS_PARAMETER.'<param name="cacheControl" value="onlyIfCached" />'."\n";

		return $this->checkLoggable().$param;
	}

	protected function checkLoggable(): string
	{
		if (($this->item['flags'] & ItemFlags::loggable->value) > 0)
			return self::TABSTOPS_PARAMETER.'<param name="logContentId" value="'. $this->item['item_id'].'" />'."\n";

		return '';
	}

}