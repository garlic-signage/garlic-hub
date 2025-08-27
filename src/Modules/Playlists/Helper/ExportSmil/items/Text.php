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
 * Represents a text-based media object capable of generating SMIL (Synchronized Multimedia Integration Language) tags.
 * This class extends the base Media functionality, adding specific behavior for text media.
 */
class Text extends Media
{

	public function createSmilTag(): string
	{
		$ret  = self::TABSTOPS_TAG.'<ref ' . $this->collectMediaAttributes(). '>' . "\n";
		$ret .= $this->checkLoggable();
		$ret .= self::TABSTOPS_TAG.'</ref>'."\n";
		return $ret;
	}

	protected function collectMediaAttributes(): string
	{
		return parent::collectMediaAttributes().'type="text/html"';
	}
}