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

namespace App\Framework\Utils\FormParameters;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;

/**
 * Represents a base class for handling edit parameters in a module, extending the base functionalities
 * of the `BaseParameters` class. This includes managing session data and defining a structured way
 * to handle and validate parameters such as CSRF tokens.
 */
abstract class BaseEditParameters extends BaseParameters
{
	protected Session $session;
	public const string PARAMETER_CSRF_TOKEN  = 'csrf_token';

	/** @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed}> */
	protected array $defaultParameters = [
		self::PARAMETER_CSRF_TOKEN  => ['scalar_type'  => ScalarType::STRING, 'default_value' => '', 'parsed' => false]
	];

	public function __construct(string $moduleName, Sanitizer $sanitizer, Session $session)
	{
		$this->session = $session;
		parent::__construct($moduleName, $sanitizer);
	}

	/**
	 * @throws ModuleException
	 */
	public function getCsrfToken(): string
	{
		if (!$this->hasParameter(self::PARAMETER_CSRF_TOKEN))
			return '';

		return $this->getValueOfParameter(self::PARAMETER_CSRF_TOKEN);
	}
}