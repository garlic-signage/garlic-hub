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
use App\Framework\Exceptions\FrameworkException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;

/**
 * Abstract class AbstractAclValidator.
 *
 * Provides methods to validate access control levels (ACL) for a specific module.
 * Handles determining different types of administrative, editor, and viewer permissions
 * and their access on specific entities such as companies or units.
 */
abstract class AbstractAclValidator
{
	protected AclHelper $helper;
	protected string $moduleName;

	public function __construct(string $moduleName, AclHelper $helper)
	{
		$this->helper        = $helper;
		$this->moduleName    = $moduleName;
	}

	public function getModuleName(): string
	{
		return $this->moduleName;
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isModuleAdmin(int $UID): bool
	{
		return $this->helper->isModuleAdmin($UID, $this->moduleName);
	}

	public function getConfig(): Config
	{
		return $this->helper->getConfig();
	}
	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isSubAdmin(int $UID): bool
	{
		return $this->helper->isSubAdmin($UID, $this->moduleName);
	}

	/**
	 * @param array{UID: int, company_id?: int, ...} $unitData
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function isAdmin(int $UID, array $unitData): bool
	{
		// module admin is always allowed
		if ($this->isModuleAdmin($UID))
			return true;

		if ($this->getConfig()->getEdition() === Config::PLATFORM_EDITION_EDGE)
			return false;

		if (!array_key_exists('company_id', $unitData) || !array_key_exists('UID', $unitData))
			throw new FrameworkException('Missing company id or UID in unit data.');

		if ($this->isSubadminWithAccessOnCompany($UID, $unitData['company_id']))
			return true;


		return false;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function isSimpleAdmin(int $UID): bool
	{
		if ($this->helper->isModuleAdmin($UID, $this->moduleName) || $this->helper->isSubAdmin($UID, $this->moduleName))
			return true;

		return false;
	}

	/**
	 * @param int $UID
	 * @return bool
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isEditor(int $UID): bool
	{
		return $this->helper->isEditor($UID, $this->moduleName);
	}

	/**
	 * @param int $UID
	 * @return bool
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isViewer(int $UID): bool
	{
		return $this->helper->isViewer($UID, $this->moduleName);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws CoreException
	 */
	public function isSubAdminWithAccessOnCompany(int $UID, int $companyId): bool
	{
		return ($this->isSubAdmin($UID) && $this->helper->hasSubAdminAccessOnCompany($UID, $companyId, $this->moduleName));
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws CoreException
	 */
	public function isEditorWithAccessOnUnit(int $UID, int|string $unitId): bool
	{
		return ($this->isEditor($UID) && $this->helper->hasEditorAccessOnUnit($UID, $unitId, $this->moduleName));
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws CoreException
	 */
	public function isViewerWithAccessOnUnit(int $UID, int|string $unitId): bool
	{
		return ($this->isViewer($UID) && $this->helper->hasViewerAccessOnUnit($UID, $unitId, $this->moduleName));
	}
}