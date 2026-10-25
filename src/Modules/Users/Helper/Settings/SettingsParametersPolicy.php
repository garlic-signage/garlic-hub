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

namespace App\Modules\Users\Helper\Settings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;

/**
 * Decides which form parameters the current user is allowed to edit.
 */
readonly class SettingsParametersPolicy
{
	public function __construct(private Parameters   $parameters,
								private AclValidator $aclValidator,
								private UserSession  $userSession)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	public function mayCreate(): bool
	{
		return $this->aclValidator->isSimpleAdmin($this->userSession->getUID());
	}

	/**
	 * @param array{UID: int, company_id: int, ...} $user
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function mayEdit(array $user): bool
	{
		return $this->aclValidator->isAdmin($this->userSession->getUID(), $user);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function addCreateFormElements(): void
	{
		if (!$this->mayCreate())
			return;

		$this->addUserFormElements(true);
	}

    /**
     * @param array{UID: int, company_id: int, ...} $user
     * @throws CoreException
     * @throws Exception
     * @throws UserException
     * @throws FrameworkException
     */
	public function addEditFormElements(array $user): void
	{
		if (!$this->mayEdit($user))
			return;

		// who blocks or demotes himself, is locked out
		$this->addUserFormElements($user['UID'] !== $this->userSession->getUID());
	}

	private function addUserFormElements(bool $withStatus): void
	{
		$this->parameters->addUserName();
		$this->parameters->addUserEmail();
		if ($withStatus)
			$this->parameters->addUserStatus();
		$this->parameters->addUserLocale();
	}
}
