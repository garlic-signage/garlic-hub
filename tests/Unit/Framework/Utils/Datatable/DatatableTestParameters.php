<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Utils\Datatable;

use App\Framework\Http\Session;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Framework\Validation\Sanitizer;

/**
 * Filter parameters of a test datatable: the standard ones for page, per page and sort and the filters name and type.
 * Nothing is parsed yet, the builder does it with the input of the request.
 */
class DatatableTestParameters extends BaseFilterParameters
{
	public function __construct(Session $session, string $sessionKey = '')
	{
		parent::__construct('testmodule', new Sanitizer(), $session, $sessionKey);

		$this->currentParameters = $this->defaultParameters;
		$this->userInputs        = [];
		$this->addParameter('name', ScalarType::STRING, '')->addParameter('type', ScalarType::STRING, '');
		$this->setDefaultForParameter(self::PARAMETER_SORT_COLUMN, 'name');
	}
}
