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

namespace App\Modules\Playlists\Helper\Settings;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\ScalarType;

/**
 * Handles the parameters for managing playlists.
 *
 * This class is responsible for defining and managing parameters related
 * to playlists such as playlist ID, name, mode, and time limits.
 * It extends BaseEditParameters to inherit core parameter management functionality.
 *
 * Constants defined within the class specify the names of these parameters.
 * Methods within the class allow dynamically adding supported parameters.
 */
class Parameters extends BaseEditParameters
{
	public const string PARAMETER_PLAYLIST_ID = 'playlist_id';
	public const string PARAMETER_PLAYLIST_NAME = 'playlist_name';
	public const string PARAMETER_PLAYLIST_MODE = 'playlist_mode';
	public const string PARAMETER_TIME_LIMIT = 'time_limit';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_PLAYLIST_NAME => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false]
	];

	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('playlists', $sanitizer, $session);
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);
	}

	public function addPlaylistMode(): void
	{
		$this->addParameter(self::PARAMETER_PLAYLIST_MODE, ScalarType::STRING, '');
	}

	/**
	 */
	public function addPlaylistId(): void
	{
		$this->addParameter(self::PARAMETER_PLAYLIST_ID, ScalarType::INT, 0);
	}

	/**
	 */
	public function addTimeLimit(): void
	{
		$this->addParameter(self::PARAMETER_TIME_LIMIT, ScalarType::INT, 0);
	}

}