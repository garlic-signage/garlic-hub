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

namespace App\Modules\Profile\Helper\Password;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\ScalarType;

/**
 * Extends the BaseEditParameters class and manages parameters specific to profile-related operations.
 * Provides predefined constants for password-related parameters and mechanisms to handle them within the context of the application.
 */
class Parameters extends BaseEditParameters
{
	public const string PARAMETER_PASSWORD           = 'password';
	public const string PARAMETER_PASSWORD_CONFIRM   = 'password_confirm';
	public const string PARAMETER_PASSWORD_TOKEN     = 'token';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: string|int, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_PASSWORD  => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_PASSWORD_CONFIRM  => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
	];

	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('profile', $sanitizer, $session);
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);
	}

	public function addToken(): void
	{
		$this->addParameter(self::PARAMETER_PASSWORD_TOKEN, ScalarType::STRING, '');
	}
}