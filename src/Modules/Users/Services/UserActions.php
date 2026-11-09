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

namespace App\Modules\Users\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Shared\User\UserStatus;
use App\Shared\Acl\AclGrantService;
use Doctrine\DBAL\Exception;

/**
 * What the current user may do with a user of the list: the entries of the context menu.
 * The list and the acls endpoint use it, so the button and the menu say the same.
 */
readonly class UserActions
{
	public function __construct(
		private AclValidator $aclValidator,
		private AclGrantService $aclGrantService
	) {}

	/**
	 * A deleted user is anonymized: nothing to do with him. Nobody deletes himself or changes his own rights.
	 *
	 * @return array{can_edit:bool, can_rights:bool, can_delete:bool}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function determine(int $currentUID, int $UID, int $companyId, int $status): array
	{
		if ($status === UserStatus::DELETED->value)
			return ['can_edit' => false, 'can_rights' => false, 'can_delete' => false];

		$canEdit = $this->aclValidator->isAdmin($currentUID, ['UID' => $UID, 'company_id' => $companyId]);

		return [
			'can_edit'   => $canEdit,
			'can_rights' => $UID !== $currentUID && $this->aclGrantService->findGrantableModules($currentUID, $companyId) !== [],
			// the installation would be without admin
			'can_delete' => $canEdit && $UID !== $currentUID && $this->aclValidator->isModuleAdmin($currentUID)
		];
	}

	/**
	 * @param array{can_edit:bool, can_rights:bool, can_delete:bool} $actions
	 */
	public static function hasAny(array $actions): bool
	{
		return in_array(true, $actions, true);
	}
}
