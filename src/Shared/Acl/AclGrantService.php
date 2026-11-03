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

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserVipRepository;
use App\Shared\User\UsersService;
use Doctrine\DBAL\Exception;
use Throwable;

/**
 * The only place which writes the rights: the role bits in user_acl and the units in user_vip.
 *
 * - A module admin grants every role of his module.
 * - A sub admin (not in Edge) grants editor and viewer, for users and units of his companies.
 * - Nobody changes his own rights.
 * - Units are granted only to users who have the role bit already.
 * - A user has at most one role on a unit, an editor may use it, too.
 */
class AclGrantService
{
	private const string MODULE_NAME = 'users';
	private const array UNIT_ROLES = [AclSections::EDITOR, AclSections::VIEWER];

	/**
	 * @param array<string,AclUnitProvider> $unitProviders module name => provider
	 */
	public function __construct(
		private readonly AclHelper $aclHelper,
		private readonly UserAclRepository $userAclRepository,
		private readonly UserVipRepository $userVipRepository,
		private readonly UsersService $usersService,
		private readonly Transactions $transactions,
		private readonly array $unitProviders
	) {}

	/**
	 * The roles the module knows, the sub admin only outside Edge.
	 *
	 * @return list<AclSections>
	 */
	public function getModuleRoles(string $module): array
	{
		$isEdge = $this->aclHelper->getConfig()->getEdition() === Config::PLATFORM_EDITION_EDGE;
		$roles  = [];
		foreach (AclSections::cases() as $role)
		{
			if ($isEdge && $role === AclSections::SUBADMIN)
				continue;

			if ($this->aclHelper->getAclBit($module, $role) > 0)
				$roles[] = $role;
		}

		return $roles;
	}

	/**
	 * Sets or removes the role bit. Removing it removes the units of the role, too.
	 *
	 * @throws AccessDeniedException
	 * @throws NotFoundException
	 * @throws TranslatableModuleException
	 * @throws Throwable
	 */
	public function setRole(int $granterUID, int $UID, string $module, AclSections $role, bool $granted): void
	{
		$this->assertRoleInModule($module, $role);
		$this->assertMayGrant($granterUID, $UID, $module, $role, [$this->findCompanyOfUser($UID)]);

		$this->transactions->begin();
		try
		{
			$bit = $this->aclHelper->getAclBit($module, $role);
			$acl = $this->userAclRepository->findAcl($UID, $module);
			$this->userAclRepository->saveAcl($UID, $module, $granted ? $acl | $bit : $acl & ~$bit);

			if (!$granted)
				$this->userVipRepository->deleteBy(['UID' => $UID, 'vip_name' => $this->aclHelper->getVipName($module, $role)]);

			$this->usersService->invalidateCache($UID);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * Sets the role of the user on the unit, null removes it. An editor may use the unit too,
	 * so a user has at most one role on a unit: the new role replaces the other one.
	 *
	 * @throws AccessDeniedException
	 * @throws NotFoundException
	 * @throws TranslatableModuleException
	 * @throws Throwable
	 */
	public function setUnitRole(int $granterUID, int $UID, string $module, int|string $unitId, ?AclSections $role): void
	{
		$unit        = $this->findUnit($module, $unitId);
		$companyIds  = [$this->findCompanyOfUser($UID), $unit['company_id']];
		$unitRoles   = $this->getUnitRoles($module);
		if ($role !== null && !in_array($role, $unitRoles, true))
			throw new TranslatableModuleException(self::MODULE_NAME, 'role_not_available', ['module' => $module, 'role' => $role->value]);

		foreach ($unitRoles as $unitRole)
		{
			$this->assertMayGrant($granterUID, $UID, $module, $unitRole, $companyIds);
		}

		if ($role !== null && ($this->userAclRepository->findAcl($UID, $module) & $this->aclHelper->getAclBit($module, $role)) === 0)
			throw new TranslatableModuleException(self::MODULE_NAME, 'user_without_role', ['UID' => $UID, 'role' => $role->value]);

		$this->transactions->begin();
		try
		{
			foreach ($unitRoles as $unitRole)
			{
				$this->userVipRepository->deleteBy(['UID' => $UID, 'vip_name' => $this->aclHelper->getVipName($module, $unitRole), 'unit_id' => (string) $unitId]);
			}

			if ($role !== null)
				$this->userVipRepository->insert(['UID' => $UID, 'vip_name' => $this->aclHelper->getVipName($module, $role), 'unit_id' => (string) $unitId]);

			$this->usersService->invalidateCache($UID);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * For the context menu of a unit: may the user open the share dialog?
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	public function canShareUnit(int $granterUID, string $module, int|string $unitId): bool
	{
		if (!isset($this->unitProviders[$module]))
			return false;

		try
		{
			return $this->findGrantableUnitRoles($granterUID, $module, $unitId) !== [];
		}
		catch (NotFoundException)
		{
			return false;
		}
	}

	/**
	 * Like canShareUnit() for a unit whose company is known already, e.g. every directory of the
	 * mediapool tree: no query for the unit.
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	public function canShareInCompany(int $granterUID, string $module, int $companyId): bool
	{
		return isset($this->unitProviders[$module]) && $this->findGrantableRolesInCompany($granterUID, $module, $companyId) !== [];
	}

	/**
	 * Everything the share dialog of a unit needs: the roles the granter may give on the unit
	 * and every user who has one of them as bit, with his roles and his current role on the unit.
	 *
	 * @return array{roles: list<AclSections>, users: list<array{UID:int, username:string, roles:list<AclSections>, role:?AclSections}>}
	 * @throws AccessDeniedException
	 * @throws NotFoundException
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function findUnitShares(int $granterUID, string $module, int|string $unitId): array
	{
		$roles = $this->findGrantableUnitRoles($granterUID, $module, $unitId);
		if ($roles === [])
			throw new AccessDeniedException(self::MODULE_NAME, 'rights_not_grantable', ['module' => $module]);

		$current = [];
		foreach ($this->findUsersOfUnit($granterUID, $module, $unitId) as $row)
		{
			$current[$row['UID']] = $row['role'];
		}

		$users = [];
		foreach ($roles as $role)
		{
			foreach ($this->findUsersWithRole($granterUID, $module, $role) as $user)
			{
				$users[$user['UID']] ??= ['UID' => $user['UID'], 'username' => $user['username'], 'roles' => [], 'role' => $current[$user['UID']] ?? null];
				$users[$user['UID']]['roles'][] = $role;
			}
		}
		usort($users, fn(array $a, array $b) => strcasecmp($a['username'], $b['username']));

		return ['roles' => $roles, 'users' => $users];
	}

	/**
	 * The users who may get the role on units, for the share dialog of a unit.
	 *
	 * @return list<array{UID:int, username:string, company_id:int}>
	 * @throws AccessDeniedException
	 * @throws TranslatableModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function findUsersWithRole(int $granterUID, string $module, AclSections $role): array
	{
		$this->assertUnitRole($module, $role);
		if (!$this->mayGrantAtAll($granterUID, $module))
			throw new AccessDeniedException(self::MODULE_NAME, 'rights_not_grantable', ['module' => $module]);

		$users = $this->userAclRepository->findUsersWithBit($module, $this->aclHelper->getAclBit($module, $role));

		return array_values(array_filter($users, fn(array $user) => $user['UID'] !== $granterUID && $this->mayGrant($granterUID, $module, $role, [$user['company_id']])));
	}

	/**
	 * Who has which role on the unit.
	 *
	 * @return list<array{UID:int, role:AclSections}>
	 * @throws AccessDeniedException
	 * @throws NotFoundException
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function findUsersOfUnit(int $granterUID, string $module, int|string $unitId): array
	{
		$unit  = $this->findUnit($module, $unitId);
		$roles = [];
		foreach ($this->getUnitRoles($module) as $role)
		{
			if ($this->mayGrant($granterUID, $module, $role, [$unit['company_id']]))
				$roles[$this->aclHelper->getVipName($module, $role)] = $role;
		}

		if ($roles === [])
			throw new AccessDeniedException(self::MODULE_NAME, 'rights_not_grantable', ['module' => $module]);

		$users = [];
		foreach ($this->userVipRepository->findUsersOfUnit(array_keys($roles), (string) $unitId) as $row)
		{
			$users[] = ['UID' => $row['UID'], 'role' => $roles[$row['vip_name']]];
		}

		return $users;
	}

	/**
	 * The units of a user in the role, for the rights page of a user.
	 *
	 * @return list<string>
	 * @throws AccessDeniedException
	 * @throws NotFoundException
	 * @throws TranslatableModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function findUnitsOfUser(int $granterUID, int $UID, string $module, AclSections $role): array
	{
		$this->assertUnitRole($module, $role);
		if (!$this->mayGrant($granterUID, $module, $role, [$this->findCompanyOfUser($UID)]))
			throw new AccessDeniedException(self::MODULE_NAME, 'rights_not_grantable', ['module' => $module]);

		return $this->userVipRepository->findUnitIds($UID, $this->aclHelper->getVipName($module, $role));
	}

	/**
	 * The roles the granter may give on the unit.
	 *
	 * @return list<AclSections>
	 * @throws NotFoundException
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	private function findGrantableUnitRoles(int $granterUID, string $module, int|string $unitId): array
	{
		$unit = $this->findUnit($module, $unitId);

		return $this->findGrantableRolesInCompany($granterUID, $module, $unit['company_id']);
	}

	/**
	 * @return list<AclSections>
	 * @throws CoreException
	 * @throws Exception
	 */
	private function findGrantableRolesInCompany(int $granterUID, string $module, int $companyId): array
	{
		return array_values(array_filter(
			$this->getUnitRoles($module),
			fn(AclSections $role) => $this->mayGrant($granterUID, $module, $role, [$companyId])
		));
	}

	/**
	 * Editor and viewer, as far as the module knows them.
	 *
	 * @return list<AclSections>
	 */
	private function getUnitRoles(string $module): array
	{
		return array_values(array_filter($this->getModuleRoles($module), fn(AclSections $role) => in_array($role, self::UNIT_ROLES, true)));
	}

	/**
	 * @param list<int> $companyIds the companies of the user and the unit
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 */
	private function assertMayGrant(int $granterUID, int $UID, string $module, AclSections $role, array $companyIds): void
	{
		if ($granterUID === $UID || !$this->mayGrant($granterUID, $module, $role, $companyIds))
			throw new AccessDeniedException(self::MODULE_NAME, 'rights_not_grantable', ['UID' => $UID, 'module' => $module]);
	}

	/**
	 * @param list<int> $companyIds all of them must be in the scope of a sub admin
	 * @throws CoreException
	 * @throws Exception
	 */
	private function mayGrant(int $granterUID, string $module, AclSections $role, array $companyIds): bool
	{
		if ($this->aclHelper->isModuleAdmin($granterUID, $module))
			return true;

		if (!in_array($role, self::UNIT_ROLES, true) || !$this->isSubAdminOutsideEdge($granterUID, $module))
			return false;

		foreach ($companyIds as $companyId)
		{
			if (!$this->aclHelper->hasSubAdminAccessOnCompany($granterUID, $companyId, $module))
				return false;
		}

		return true;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	private function mayGrantAtAll(int $granterUID, string $module): bool
	{
		return $this->aclHelper->isModuleAdmin($granterUID, $module) || $this->isSubAdminOutsideEdge($granterUID, $module);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	private function isSubAdminOutsideEdge(int $granterUID, string $module): bool
	{
		return $this->aclHelper->getConfig()->getEdition() !== Config::PLATFORM_EDITION_EDGE
			&& $this->aclHelper->isSubAdmin($granterUID, $module);
	}

	/**
	 * @throws TranslatableModuleException
	 */
	private function assertRoleInModule(string $module, AclSections $role): void
	{
		if (!in_array($role, $this->getModuleRoles($module), true))
			throw new TranslatableModuleException(self::MODULE_NAME, 'role_not_available', ['module' => $module, 'role' => $role->value]);
	}

	/**
	 * Only editors and viewers have units. A sub admin has companies, they come with the Enterprise edition.
	 *
	 * @throws TranslatableModuleException
	 */
	private function assertUnitRole(string $module, AclSections $role): void
	{
		$this->assertRoleInModule($module, $role);
		if (!in_array($role, self::UNIT_ROLES, true))
			throw new TranslatableModuleException(self::MODULE_NAME, 'role_not_available', ['module' => $module, 'role' => $role->value]);
	}

	/**
	 * @return array{UID:int, company_id:int}
	 * @throws NotFoundException
	 * @throws ModuleException
	 * @throws Exception
	 */
	private function findUnit(string $module, int|string $unitId): array
	{
		if (!isset($this->unitProviders[$module]))
			throw new ModuleException(self::MODULE_NAME, 'No unit provider for module ' . $module);

		$unit = $this->unitProviders[$module]->findUnit($unitId);
		if ($unit === [])
			throw new NotFoundException(self::MODULE_NAME, 'unit_not_found', ['module' => $module, 'unit_id' => (string) $unitId]);

		return ['UID' => (int) $unit['UID'], 'company_id' => (int) $unit['company_id']];
	}

	/**
	 * @throws NotFoundException
	 * @throws Exception
	 */
	private function findCompanyOfUser(int $UID): int
	{
		$main = $this->usersService->getUserById($UID)->getMain();
		if ($main === [])
			throw new NotFoundException(self::MODULE_NAME, 'user_not_found', ['UID' => $UID]);

		return (int) $main['company_id'];
	}
}
