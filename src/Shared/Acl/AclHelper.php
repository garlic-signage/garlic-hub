<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Shared\User\UsersService;
use Doctrine\DBAL\Exception;

/**
 * A helper class for managing Access Control Level (ACL) operations.
 * This class provides methods to verify different access levels such as module admin,
 * sub-admin, editor, viewer, and more.
 */
class AclHelper
{
	protected const string SECTION_GLOBAL_ACLS = 'GlobalACLs';
	protected readonly UsersService $userService;
	protected readonly Config $config;

	public function __construct(UsersService $userService, Config $config)
	{
		$this->userService   = $userService;
		$this->config        = $config;
	}

	public function getConfig(): Config
	{
		return $this->config;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isModuleAdmin(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::MODULEADMIN, $moduleName);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isSubAdmin(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::SUBADMIN, $moduleName);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isEditor(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::EDITOR, $moduleName);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isViewer(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::VIEWER, $moduleName);
	}

	/**
	 * @throws Exception
	 */
	public function hasSubAdminAccessOnCompany(int $UID, int $companyId, string $moduleName): bool
	{
		return $this->hasVip($UID, $this->getVipName($moduleName, AclSections::SUBADMIN), $companyId);
	}

	/**
	 * @throws Exception
	 */
	public function hasEditorAccessOnUnit(int $UID, int|string $unitId, string $moduleName): bool
	{
		return $this->hasVip($UID, $this->getVipName($moduleName, AclSections::EDITOR), $unitId);
	}

	/**
	 * @throws Exception
	 */
	public function hasViewerAccessOnUnit(int $UID, int|string $unitId, string $moduleName): bool
	{
		return $this->hasVip($UID, $this->getVipName($moduleName, AclSections::VIEWER), $unitId);

	}

	/**
	 * The bit of the role in user_acl. 0 when the module does not know the role.
	 */
	public function getAclBit(string $moduleName, AclSections $role): int
	{
		return (int) $this->config->getConfigValue($role->value, $moduleName, self::SECTION_GLOBAL_ACLS);
	}

	/**
	 * The units shared with the user in one of the roles, from the cached user.
	 *
	 * @param list<string> $vipNames
	 * @return list<string>
	 * @throws Exception
	 */
	public function findSharedUnitIds(int $UID, array $vipNames): array
	{
		$unitIds = [];
		foreach ($this->userService->getUserById($UID)->getVip() as $vip)
		{
			if (in_array($vip['vip_name'] ?? '', $vipNames, true))
				// @infection-ignore-all the column is text
				$unitIds[] = (string) ($vip['unit_id'] ?? '');
		}

		return array_values(array_unique($unitIds));
	}

	/**
	 * The name of the role in user_vip, e.g. player_editor.
	 */
	public function getVipName(string $moduleName, AclSections $role): string
	{
		return $moduleName . '_' . $role->value;
	}

	/**
	 * @throws Exception
	 */
	private function hasVip(int $UID, string $vipName, int|string $id): bool
	{
		$userEntity = $this->userService->getUserById($UID);
		$vips = $userEntity->getVip();
		// unit_id is a varchar, so ids are compared as strings
		foreach ($vips as $vip)
		{
			if (($vip['vip_name'] ?? '') === $vipName && (string) ($vip['unit_id'] ?? '') === (string) $id)
				return true;
		}

		return false;

	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	private function hasGlobalAcl(int $UID, AclSections $role, string $moduleName): bool
	{
		$userEntity = $this->userService->getUserById($UID);
		$acls       = $userEntity->getAcl();

		$aclValue = $this->getAclBit($moduleName, $role);

		foreach ($acls as $acl)
		{
			if ($acl['module'] === $moduleName && ($acl['acl'] & $aclValue) > 0)
				return true;
		}

		return false;
	}

}