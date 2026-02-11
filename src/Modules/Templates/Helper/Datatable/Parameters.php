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

namespace App\Modules\Templates\Helper\Datatable;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\ScalarType;

/**
 * Extends the BaseFilterParameters class and manages specific parameters related to players.
 * Defines module-specific parameters and provides functionality to add additional parameters.
 */
class Parameters extends BaseFilterParameters
{
	const string PARAMETER_TEMPLATE_NAME = 'name';
	const string PARAMETER_TYPE = 'type';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_TEMPLATE_NAME => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_TYPE => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false]
	];

	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('player', $sanitizer, $session, 'templates_filter');
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);
	}

}