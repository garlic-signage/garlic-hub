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

namespace App\Modules\Player\Helper\Datatable;

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
	const string PARAMETER_ACTIVITY = 'activity';
	const string PARAMETER_PLAYER_NAME = 'player_name';
	const string PARAMETER_UUID = 'uuid';
	const string PARAMETER_MODEL = 'model';
	const string PARAMETER_PLAYER_ID = 'player_id';
//	const string PARAMETER_PLAYLIST_ID = 'playlist_id';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_PLAYER_NAME => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_MODEL => ['scalar_type' => ScalarType::INT, 'default_value' => 0, 'parsed' => false],
		self::PARAMETER_UUID => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
	];

	/**
	 * @throws ModuleException
	 */
	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('player', $sanitizer, $session, 'player_filter');
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);

		$this->setDefaultForParameter(self::PARAMETER_SORT_COLUMN, self::PARAMETER_PLAYER_ID);
	}

	public function addActivity(): void
	{
		$this->addParameter(self::PARAMETER_ACTIVITY, ScalarType::STRING, '');
	}
}