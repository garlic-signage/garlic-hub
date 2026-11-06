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
 * Which LLM a user works with. While his company has one, only this one counts: for its admins or for all users,
 * as it is released. The own LLMs of the users are not used then. Without an LLM of the company, the own one.
 * An admin is a module admin or sub admin of any module.
 */
readonly class LlmResolver
{
	public function __construct(
		private UserLlmService $userLlmService,
		private CompanyLlmService $companyLlmService,
		private LlmClientFactory $clientFactory,
		private UsersService $usersService,
		private AclHelper $aclHelper
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isAvailable(int $UID): bool
	{
		return $this->resolve($UID) !== null;
	}

	/**
	 * For the account: the own LLM is not used while the company has one.
	 *
	 * @throws Exception
	 * @throws UserException
	 */
	public function hasCompanyLlm(int $UID): bool
	{
		return $this->companyLlmService->findReleasedFor($this->companyOf($UID)) !== null;
	}

	/**
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function forUser(int $UID): LlmClient
	{
		$resolved = $this->resolve($UID);
		$connection = $resolved === null ? null : $resolved[0]->findConnection($resolved[1]);
		if ($connection === null)
			throw new LlmException('llm_not_configured');

		return $this->clientFactory->create($connection);
	}

	/**
	 * @return array{LlmSettings, int}|null the settings and the owner of the LLM
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	private function resolve(int $UID): ?array
	{
		$companyId   = $this->companyOf($UID);
		$releasedFor = $this->companyLlmService->findReleasedFor($companyId);
		if ($releasedFor !== null)
			return $releasedFor === CompanyLlmService::RELEASED_FOR_ALL || $this->isAdmin($UID) ? [$this->companyLlmService, $companyId] : null;

		return $this->userLlmService->findSettings($UID) === null ? null : [$this->userLlmService, $UID];
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
