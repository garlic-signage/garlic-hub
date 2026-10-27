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

namespace App\Modules\Playlists\Helper;

/**
 * The file name of the rendered image of a template item: <item_id>-<hmac of the content>.
 *
 * public/delivery.php delivers these images to the players without a login, the hmac makes the
 * names impossible to guess from the item ids. Every content has a name of its own, so a file
 * never changes: the players get a new content first with the export which links to it.
 */
readonly class TemplateItemName
{
	public function __construct(private string $secret)
	{}

	public function build(int $itemId, string $content): string
	{
		return $itemId . '-' . substr(hash_hmac('sha256', $itemId . '|' . $content, $this->secret), 0, 32);
	}
}
