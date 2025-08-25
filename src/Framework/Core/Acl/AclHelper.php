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

namespace App\Framework\Core\Acl;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Modules\Users\Services\UsersService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;

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
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isModuleAdmin(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::MODULEADMIN->value, $moduleName);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isSubAdmin(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::SUBADMIN->value, $moduleName);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isEditor(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::EDITOR->value, $moduleName);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isViewer(int $UID, string $moduleName): bool
	{
		return $this->hasGlobalAcl($UID, AclSections::VIEWER->value, $moduleName);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function hasSubAdminAccessOnCompany(int $UID, int $companyId, string $moduleName): bool
	{
		return $this->hasVip($UID,$moduleName.'_'.AclSections::SUBADMIN->value, $companyId);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function hasEditorAccessOnUnit(int $UID, int|string $unitId, string $moduleName): bool
	{
		return $this->hasVip($UID,$moduleName.'_'.AclSections::EDITOR->value, $unitId);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function hasViewerAccessOnUnit(int $UID, int|string $unitId, string $moduleName): bool
	{
		return $this->hasVip($UID,$moduleName.'_'.AclSections::VIEWER->value, $unitId);

	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	private function hasVip(int $UID, string $vipName, int|string $id): bool
	{
		$userEntity = $this->userService->getUserById($UID);
		$vips = $userEntity->getVip();
		foreach ($vips as $vip)
		{
			if (isset($vip[$vipName]) && $vip[$vipName] === $id)
				return true;
		}

		return false;

	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	private function hasGlobalAcl(int $UID, string $aclName, string $moduleName): bool
	{
		$userEntity = $this->userService->getUserById($UID);
		$acls       = $userEntity->getAcl();

		$aclValue = (int) $this->config->getConfigValue($aclName, $moduleName, self::SECTION_GLOBAL_ACLS);

		foreach ($acls as $acl)
		{
			if ($acl['module'] === $moduleName && ($acl['acl'] & $aclValue) > 0)
				return true;
		}

		return false;
	}

}