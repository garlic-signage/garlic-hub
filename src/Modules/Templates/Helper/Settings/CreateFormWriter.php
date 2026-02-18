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
use App\Modules\Templates\Services\TemplateService;

class CreateFormWriter
{
	public function __construct(
		private readonly UserSession $userSession,
		private readonly TemplateService   $templatesService,
	) {}



	public function store(array $saveData): array
	{
		if ($this->templatesService->setUID($this->userSession->getUID())->insert($saveData) === 0 )
			return ['Not able to save'];

		return [];
	}

}