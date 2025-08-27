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

namespace App\Modules\Playlists\Collector\Builder;

use App\Modules\Playlists\Helper\ExportSmil\items\Base;

/**
 * Helper class for formatting content into specific sequences or modifying region attributes for multi-zone displays.
 */
class FormatHelper
{
	public static function wrapWithSequence(string $content): string
	{
		return Base::TABSTOPS_TAG . '<seq repeatCount="indefinite">' . "\n" .
			$content .
			Base::TABSTOPS_TAG . '</seq>' . "\n";
	}

	public static function formatMultiZoneItems(int $screenId, string $items): string
	{
		$replaced = str_replace('region="screen"', 'region="screen' . $screenId . '"', $items);

		return Base::TABSTOPS_TAG . '<seq id="media' . $screenId . '" repeatCount="indefinite">' . "\n" .
			$replaced .
			Base::TABSTOPS_TAG . '</seq>' . "\n";
	}

	public static function formatMultiZoneExclusive(int $screenId, string $exclusive): string
	{
		return str_replace('region="screen"', 'region="screen' . $screenId . '"', $exclusive);
	}
}
