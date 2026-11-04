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

namespace App\Modules\System\Helper\Audit;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Http\Session;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Framework\Validation\Sanitizer;

/**
 * The filter of the audit log: period, who, module and action. The newest entries come first.
 */
class Parameters extends BaseFilterParameters
{
	public const string PARAMETER_DATE_FROM = 'date_from';
	public const string PARAMETER_DATE_TO   = 'date_to';
	public const string PARAMETER_USERNAME  = 'username';
	public const string PARAMETER_MODULE    = 'module';
	public const string PARAMETER_ACTION    = 'action';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_DATE_FROM => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_DATE_TO   => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_USERNAME  => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_MODULE    => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_ACTION    => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
	];

	/**
	 * @throws ModuleException
	 */
	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('system', $sanitizer, $session, 'audit_filter');
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);

		$this->setDefaultForParameter(self::PARAMETER_SORT_COLUMN, 'created_at');
		$this->setDefaultForParameter(self::PARAMETER_SORT_ORDER, 'DESC');
	}
}
