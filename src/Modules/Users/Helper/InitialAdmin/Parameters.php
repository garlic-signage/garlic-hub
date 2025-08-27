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

namespace App\Modules\Users\Helper\InitialAdmin;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\ScalarType;

/**
 * Represents a parameter set configuration for user-related administrative operations.
 *
 * This class extends the BaseEditParameters to handle specific administrative parameters
 * such as username, email, locale, password, and password confirmation. It provides
 * default values and scalar types for each parameter and initializes the parameter set
 * during object construction.
 */
class Parameters extends BaseEditParameters
{
	public const string PARAMETER_ADMIN_NAME     = 'username';
	public const string PARAMETER_ADMIN_EMAIL     = 'email';
	const string PARAMETER_ADMIN_LOCALE            = 'locale'; // the locale of the edited user
	public const string PARAMETER_ADMIN_PASSWORD           = 'password';
	public const string PARAMETER_ADMIN_PASSWORD_CONFIRM   = 'password_confirm';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: string|int, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_ADMIN_NAME  => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_ADMIN_EMAIL  => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_ADMIN_LOCALE  => ['scalar_type' => ScalarType::STRING, 'default_value' => 'en_US', 'parsed' => false],
		self::PARAMETER_ADMIN_PASSWORD  => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_ADMIN_PASSWORD_CONFIRM  => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false]
	];

	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('user', $sanitizer, $session);
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);
	}

}