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

namespace App\Modules\Player\Helper\NetworkSettings;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\ScalarType;

/**
 * Represents a set of configurable parameters for a specific module. Extends functionality from the BaseEditParameters class
 * and provides a structure for defining and managing module-specific parameters.
 *
 * This class includes predefined constants that act as identifiers for different configurable parameters.
 * It also defines a structure in the form of `$moduleParameters` that specifies metadata for each parameter such
 * as its scalar type, default value, and parsing status.
 *
 * The constructor merges these module-specific parameters with default parameters inherited from the parent class,
 * initializing the combined set of parameters for use.
 */
class Parameters extends BaseEditParameters
{
	public const string PARAMETER_API_ENDPOINT = 'api_endpoint';
	public const string PARAMETER_IS_INTRANET = 'is_intranet';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_IS_INTRANET => ['scalar_type' => ScalarType::BOOLEAN, 'default_value' => false, 'parsed' => false],
		self::PARAMETER_API_ENDPOINT=> ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false]
	];

	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('player', $sanitizer, $session);
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);
	}
}