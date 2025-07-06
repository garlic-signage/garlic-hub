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

namespace App\Modules\Users\Helper\Datatable;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\ScalarType;

class Parameters extends BaseFilterParameters
{
	const string PARAMETER_USERNAME     = 'username';
	const string PARAMETER_EMAIL        = 'email';
	const string PARAMETER_FIRSTNAME    = 'firstname';
	const string PARAMETER_SURNAME      = 'surname';
	const string PARAMETER_COMPANY_NAME = 'company_name';
	const string PARAMETER_STATUS       = 'status';

	// because we need also a db operation that includes the higher user statuses
	const string PARAMETER_FROM_STATUS  = 'from_status';
// later	const string PARAMETER_ONLINE       = 'is_online';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: string|int, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_USERNAME   => ['scalar_type'  => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_EMAIL      => ['scalar_type'  => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_STATUS     => ['scalar_type'  => ScalarType::INT,    'default_value' => 0,  'parsed' => false]
	];

	/**
	 * @throws ModuleException
	 */
	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('users', $sanitizer, $session, 'users_filter');
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);

		$this->setDefaultForParameter(self::PARAMETER_SORT_COLUMN, self::PARAMETER_UID);
	}



}