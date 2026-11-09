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


namespace App\Modules\Templates\Helper\Settings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Services\AclValidator;
use Doctrine\DBAL\Exception;

/**
 * Owner and visibility are for the admins: an editor of a template works on its content and name. Making it global
 * shares it with everybody, which only an admin may do. Creating a template is for the admins only (canCreate).
 */
readonly class SettingsParametersPolicy
{
	public function __construct(private Parameters   $parameters,
								private AclValidator $aclValidator,
								private UserSession  $userSession)
	{}

	/**
	 * @param array<string,mixed> $template with UID and company_id
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function addEditFormElements(array $template): void
	{
		$owner = ['UID' => (int) ($template['UID'] ?? 0), 'company_id' => (int) ($template['company_id'] ?? 0)];
		if (!$this->aclValidator->isAdmin($this->userSession->getUID(), $owner))
			return;

		$this->addAdminFormElements();
	}

	public function addCreateFormElements(): void
	{
		$this->addAdminFormElements();
		$this->parameters->addType();
	}

	private function addAdminFormElements(): void
	{
		$this->parameters->addOwner();
		$this->parameters->addVisibility();
	}
}
