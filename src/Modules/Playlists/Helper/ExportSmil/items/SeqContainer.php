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

/**
 * Class for exporting to SMIL container tags (seq, par, excl, priorityClass)
 */
class SeqContainer extends Base implements ItemInterface
{

	public function createSmilTag(): string
	{
		return self::TABSTOPS_TAG.'<seq '.$this->collectPlaylistAttributes().'>'."\n".
			self::TABSTOPS_PARAMETER.'{ITEMS_'. $this->item['file_resource'].'}'."\n".
			self::TABSTOPS_TAG.'</seq>'."\n";

	}

	private function collectPlaylistAttributes(): string
	{
		return $this->collectAttributes().$this->determinePlaylistDuration();
	}

	public function getElementLink(): string
	{
		return self::TABSTOPS_TAG.'{ITEMS_0#'.$this->item['external_link'].'}'."\n";
	}

	public function getPrefetchTag(): string
	{
		return  self::TABSTOPS_TAG.'{PREFETCH_'.$this->item['file_resource'].'}'."\n";
	}


	private function determinePlaylistDuration(): string
	{
		if ($this->item['time_limit'] !== null && $this->item['time_limit'] > 0 && $this->item['time_limit'] < $this->item['owner_duration'])
			return 'dur="'.$this->item['time_limit'].'s" ';

		return '';
	}
}