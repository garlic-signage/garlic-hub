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
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;

/**
 * Decides which form parameters the current user is allowed to edit.
 */
class SettingsParametersPolicy
{
	public function __construct(private readonly Parameters $parameters,
								private readonly AclValidator $aclValidator,
								private readonly UserSession $userSession)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function addCreateFormElements(): void
	{
		if (!$this->aclValidator->isSimpleAdmin($this->userSession->getUID()))
			return;

		$this->addUserFormElements();
	}

	/**
	 * @param array{UID: int, company_id: int, ...} $user
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function addEditFormElements(array $user): void
	{
		if (!$this->aclValidator->isAdmin($this->userSession->getUID(), $user))
			return;

		$this->addUserFormElements();
	}

	private function addUserFormElements(): void
	{
		$this->parameters->addUserName();
		$this->parameters->addUserEmail();
		$this->parameters->addUserStatus();
		$this->parameters->addUserLocale();
	}
}
