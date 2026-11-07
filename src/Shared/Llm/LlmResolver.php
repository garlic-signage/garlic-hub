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

namespace App\Shared\Llm;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmClient;
use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmException;
use App\Shared\Acl\AclHelper;
use App\Shared\Acl\EditionAclModules;
use App\Shared\User\UsersService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;

/**
 * Which AI a user works with for a task, as the admin of his company decided for it: the AI of the company for all
 * users or only for its admins, the own AI of the user, or none. A task without a decision is off.
 * An admin is a module admin or sub admin of any module.
 */
readonly class LlmResolver
{
	public const string ACCESS_COMPANY = 'company';
	public const string ACCESS_OWN     = 'own';
	public const string ACCESS_OFF     = 'off';

	public function __construct(
		private UserLlmService $userLlmService,
		private CompanyLlmService $companyLlmService,
		private LlmClientFactory $clientFactory,
		private UsersService $usersService,
		private AclHelper $aclHelper
	) {}

	/**
	 * Whether the helper of the task appears.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isAvailable(int $UID, string $task): bool
	{
		return match ($this->findAccess($UID)[$task] ?? self::ACCESS_OFF)
		{
			self::ACCESS_COMPANY => $this->companyLlmService->hasModelForTask($this->companyOf($UID), $task),
			self::ACCESS_OWN     => $this->userLlmService->hasModelForTask($UID, $task),
			default              => false
		};
	}

	/**
	 * For the account: which tasks the company provides, which the user sets up himself and which are off for him.
	 *
	 * @return array<string,string> the access of every task
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function findAccess(int $UID): array
	{
		$access = [];
		foreach ($this->companyLlmService->findModes($this->companyOf($UID)) as $task => $mode)
		{
			$access[$task] = match ($mode)
			{
				CompanyLlmService::MODE_COMPANY_ALL    => self::ACCESS_COMPANY,
				CompanyLlmService::MODE_COMPANY_ADMINS => $this->isAdmin($UID) ? self::ACCESS_COMPANY : self::ACCESS_OFF,
				CompanyLlmService::MODE_USER_OWN       => self::ACCESS_OWN,
				default                                => self::ACCESS_OFF
			};
		}

		return $access;
	}

	/**
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function forUser(int $UID, string $task): LlmClient
	{
		$connection = match ($this->findAccess($UID)[$task] ?? self::ACCESS_OFF)
		{
			self::ACCESS_COMPANY => $this->companyLlmService->findConnectionOfTask($this->companyOf($UID), $task),
			self::ACCESS_OWN     => $this->userLlmService->findConnectionOfTask($UID, $task),
			default              => null
		};
		if ($connection === null)
			throw new LlmException('llm_not_configured');

		return $this->clientFactory->create($connection);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	private function isAdmin(int $UID): bool
	{
		foreach (EditionAclModules::getModules($this->aclHelper->getConfig()->getEdition()) as $module)
		{
			if ($this->aclHelper->isModuleAdmin($UID, $module) || $this->aclHelper->isSubAdmin($UID, $module))
				return true;
		}

		return false;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	private function companyOf(int $UID): int
	{
		$companyId = $this->usersService->getUserById($UID)->getMain()['company_id'] ?? 1;

		return is_numeric($companyId) ? (int) $companyId : 1;
	}
}
