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

namespace App\Modules\Templates\Contracts;

use Doctrine\DBAL\Exception;

/**
 * Implemented by modules that reference templates (e.g. Playlists), so Templates can
 * tell how often a template is used without knowing how those modules store it.
 */
interface TemplateUsageCounterInterface
{
	/**
	 * @param int[] $templateIds
	 * @return array<int,int> template id => number of usages, unused templates are missing
	 * @throws Exception
	 */
	public function countUsagesByTemplateIds(array $templateIds): array;
}
