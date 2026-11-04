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
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Doctrine\DBAL\Exception;

/**
 * Abstract class AbstractAclValidator.
 *
 * Provides methods to validate access control levels (ACL) for a specific module.
 * Handles determining different types of administrative, editor, and viewer permissions
 * and their access on specific entities such as companies or units.
 */
abstract class AbstractAclValidator
{
	// a module with units which are shared, e.g. playlist: the key of the id and of the messages
	protected const string UNIT_NAME = '';

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
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isModuleAdmin(int $UID): bool
	{
		return $this->helper->isModuleAdmin($UID, $this->moduleName);
	}

	/**
	 * The names of the roles in user_vip, e.g. playlists_editor.
	 *
	 * @return list<string>
	 */
	public function getVipNames(AclSections ...$roles): array
	{
		return array_values(array_map(fn(AclSections $role) => $this->helper->getVipName($this->moduleName, $role), $roles));
	}

	/**
	 * @return list<string>
	 * @throws Exception
	 */
	public function findSharedUnitIds(int $UID, AclSections ...$roles): array
	{
		return $this->helper->findSharedUnitIds($UID, $this->getVipNames(...$roles));
	}

	public function getConfig(): Config
	{
		return $this->helper->getConfig();
	}
	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isSubAdmin(int $UID): bool
	{
		return $this->helper->isSubAdmin($UID, $this->moduleName);
	}

	/**
	 * @param array{UID: int, company_id?: int, ...} $unitData
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function isAdmin(int $UID, array $unitData): bool
	{
		// module admin is always allowed
		if ($this->isModuleAdmin($UID))
			return true;

		if ($this->getConfig()->getEdition() === Config::PLATFORM_EDITION_CORE)
			return false;

		if (!array_key_exists('company_id', $unitData) || !array_key_exists('UID', $unitData))
			throw new FrameworkException('Missing company id or UID in unit data.');

		if ($this->isSubadminWithAccessOnCompany($UID, $unitData['company_id']))
			return true;


		return false;
	}

	/**
	 * @throws CoreException
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
	 */
	public function isViewer(int $UID): bool
	{
		return $this->helper->isViewer($UID, $this->moduleName);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	public function isSubAdminWithAccessOnCompany(int $UID, int $companyId): bool
	{
		return ($this->isSubAdmin($UID) && $this->helper->hasSubAdminAccessOnCompany($UID, $companyId, $this->moduleName));
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	public function isEditorWithAccessOnUnit(int $UID, int|string $unitId): bool
	{
		return ($this->isEditor($UID) && $this->helper->hasEditorAccessOnUnit($UID, $unitId, $this->moduleName));
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	public function isViewerWithAccessOnUnit(int $UID, int|string $unitId): bool
	{
		return ($this->isViewer($UID) && $this->helper->hasViewerAccessOnUnit($UID, $unitId, $this->moduleName));
	}

	/**
	 * Who deletes a unit: its admins. A module whose owners delete their units, too, overrides it.
	 *
	 * @param array{UID: int, company_id: int, ...} $unit
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function isUnitDeletable(int $UID, array $unit): bool
	{
		return $this->isAdmin($UID, $unit);
	}

	/**
	 * Who deletes a unit edits it, an editor of a shared unit, too.
	 *
	 * @param array{UID: int, company_id: int, ...} $unit
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function isUnitEditable(int $UID, array $unit): bool
	{
		if ($this->isUnitDeletable($UID, $unit))
			return true;

		return $this->isEditorWithAccessOnUnit($UID, $this->unitIdOf($unit));
	}

	/**
	 * The check before a unit is edited or deleted. Editing is not enough for a delete:
	 * an editor of a shared unit must not delete it.
	 *
	 * @param array{UID: int, company_id: int, ...} $unit
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function assertUnitAccess(int $UID, array $unit, bool $forDelete): void
	{
		$replacements = [static::UNIT_NAME . '_id' => $this->unitIdOf($unit)];
		if (!$this->isUnitEditable($UID, $unit))
			throw new AccessDeniedException($this->moduleName, static::UNIT_NAME . '_not_editable', $replacements);

		if ($forDelete && !$this->isUnitDeletable($UID, $unit))
			throw new AccessDeniedException($this->moduleName, static::UNIT_NAME . '_not_deletable', $replacements);
	}

	/**
	 * @param array<string,mixed> $unit
	 */
	private function unitIdOf(array $unit): int|string
	{
		$unitId = $unit[static::UNIT_NAME . '_id'] ?? '';

		return is_int($unitId) || is_string($unitId) ? $unitId : '';
	}
}
