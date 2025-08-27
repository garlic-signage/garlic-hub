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
 * Interface ItemInterface
 *
 * Represents a contract for an item containing methods to handle various attributes and behaviors related to SMIL tags and other item-specific properties.
 */
interface ItemInterface
{
	/**
	 * @param array<int,int>|array<empty,empty> $touches
	 */
	public function setTouches(array $touches): void;

	public function getPrefetchTag(): string;

	public function getSmilElementTag(): string;

	public function getExclusive(): string;

	// public function getElementForPreview();
	public function createSmilTag(): string;
}
