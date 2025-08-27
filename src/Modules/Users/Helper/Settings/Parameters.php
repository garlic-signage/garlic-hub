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

namespace App\Modules\Users\Helper\Settings;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\ScalarType;

/**
 * Represents a class for managing user-related parameters in a module.
 * Extends the base functionality of `BaseEditParameters` to handle specific parameters
 * such as username, email, user ID, status, and locale.
 */
class Parameters extends BaseEditParameters
{
	const string PARAMETER_USER_NAME  = 'username';
	const string PARAMETER_USER_EMAIL  = 'email';
	const string PARAMETER_USER_ID  = 'UID'; // the UID of the edited user
	const string PARAMETER_USER_STATUS  = 'status'; // the UID of the edited user
	const string PARAMETER_USER_LOCALE  = 'locale'; // the locale of the edited user

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: string|int, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_USER_ID  => ['scalar_type' => ScalarType::INT, 'default_value' => 0, 'parsed' => false],
	];

	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('users', $sanitizer, $session);
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);
	}

	public function addUserName(): void
	{
		$this->addParameter(self::PARAMETER_USER_NAME, ScalarType::STRING, '');
	}

	public function addUserEmail(): void
	{
		$this->addParameter(self::PARAMETER_USER_EMAIL, ScalarType::STRING, '');
	}

	public function addUserStatus(): void
	{
		$this->addParameter(self::PARAMETER_USER_STATUS, ScalarType::INT, 0);
	}

	public function addUserLocale(): void
	{
		$this->addParameter(self::PARAMETER_USER_LOCALE, ScalarType::STRING, 'en_US');
	}


}