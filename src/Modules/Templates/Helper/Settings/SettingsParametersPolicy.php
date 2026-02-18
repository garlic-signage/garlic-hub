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

use App\Modules\Auth\UserSession;
use App\Modules\Templates\Services\AclValidator;

class SettingsParametersPolicy
{
	public function __construct(private readonly Parameters $parameters,
								private readonly AclValidator $aclValidator,
								private readonly UserSession $userSession,)
	{}


	public function checkCreateRights()
	{
		if ($this->aclValidator->canCreate($this->userSession->getUID()))
			return true;

		return false;
	}

	public function addCreateFormElements()
	{
		$this->parameters->addOwner();
		$this->parameters->addVisibility();
		$this->parameters->addType();
	}



}