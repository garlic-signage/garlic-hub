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

namespace App\Modules\Playlists\Helper\Datatable;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\FormParameters\ScalarType;

/**
 * Class Parameters
 *
 * Represents the parameters used for filtering and managing playlist-related data.
 * This class extends BaseFilterParameters and implements BaseFilterParametersInterface.
 * It is specifically designed to manage parameters associated with playlists,
 * including the playlist name, mode, and ID.
 *
 * Constants in this class define keys for playlist-specific parameters, while
 * moduleParameters holds metadata about each parameter, such as its scalar type,
 * default value, and parsing state.
 *
 * Constructor initializes the parameters, merging them with default parameters,
 * and sets default sorting behavior for the playlist ID column.
 *
 */
class Parameters extends BaseFilterParameters implements BaseFilterParametersInterface
{
	const string PARAMETER_PLAYLIST_NAME = 'playlist_name';
	const string PARAMETER_PLAYLIST_MODE = 'playlist_mode';
	const string PARAMETER_PLAYLIST_ID   = 'playlist_id';

	/**
	 * @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}>
	 */
	protected array $moduleParameters = [
		self::PARAMETER_PLAYLIST_NAME => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
		self::PARAMETER_PLAYLIST_MODE => ['scalar_type' => ScalarType::STRING,  'default_value' => '', 'parsed' => false]
	];

	/**
	 * @throws ModuleException
	 */
	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('playlists', $sanitizer, $session, 'playlists_filter');
		$this->currentParameters = array_merge($this->defaultParameters, $this->moduleParameters);

		$this->setDefaultForParameter(self::PARAMETER_SORT_COLUMN, self::PARAMETER_PLAYLIST_ID);
	}
}