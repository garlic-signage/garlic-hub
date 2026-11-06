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

namespace App\Shared\Acl;

use App\Framework\Database\Datatable\FilterDefinition;
use App\Framework\Database\Datatable\SharedUnits;
use App\Framework\Exceptions\CoreException;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Utils\FormParameters\BaseParameters;
use Doctrine\DBAL\Exception;

/**
 * The datatable of a module with units which can be shared (player, playlists, templates).
 *
 * The list for the management shows the own units and the ones shared for editing. A selection
 * (a template for a playlist, a playlist for a player) shows the ones shared for using, too:
 * an editor may use a unit, a viewer may only use it.
 */
abstract class AbstractUnitDatatableService extends AbstractDatatableService
{
	/**
	 * The units with the visibility (e.g. the global templates) are shown to everybody, to a sub admin, too.
	 *
	 * @param list<AclSections> $roles
	 * @throws CoreException
	 * @throws Exception
	 */
	protected function fetchForRoles(AbstractAclValidator $aclValidator, FilterDefinition $definition, BaseParameters $parameters, int $UID, string $idField, array $roles, ?string $visibility = null): void
	{
		if ($aclValidator->isModuleAdmin($UID))
		{
			$this->fetchForModuleAdmin($definition, $parameters);
			return;
		}

		// the scope of a sub admin comes with the Enterprise edition, until then his own units and the visible ones
		if ($aclValidator->isSubAdmin($UID))
		{
			if ($visibility !== null)
				$this->fetchForUser($definition, $parameters, $UID, new SharedUnits($idField, [], $visibility));
			return;
		}

		$this->fetchForUser($definition, $parameters, $UID, new SharedUnits($idField, $aclValidator->getVipNames(...$roles), $visibility));
	}
}
