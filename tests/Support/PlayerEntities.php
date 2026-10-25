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

namespace Tests\Support;

use App\Framework\Config\Config;
use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\IndexCreation\PlayerDetector;
use App\Modules\Player\IndexCreation\UserAgentHandler;

/**
 * Players for the tests of the index creation: the entity is the real one, what the player says about itself
 * (model, firmware, name, uuid) comes from a real user agent text.
 */
final class PlayerEntities
{
	public const string UUID = '3f0cd56c-d511-486a-a8e1-9d2cefd78b3f';

	/**
	 * @param array<string,mixed> $data the row of the database
	 */
	public static function create(array $data = [], string $model = 'Garlic', string $firmware = 'garlic-macOS/v0.6.0.679', string $name = 'Eingang', string $uuid = self::UUID): PlayerEntity
	{
		$config = new Config(new RecordingConfigLoader(['player' => ['report_server_url' => 'https://reports.example.com', 'index_server_url' => 'https://hub.example.com/smil-index']]));
		$agent  = new UserAgentHandler(new PlayerDetector($config));
		$agent->parseUserAgent('GAPI/1.0 (UUID:' . $uuid . '; NAME:' . $name . ') ' . $firmware . ' (MODEL:' . $model . ')');

		return new PlayerEntity($config, $agent, $data);
	}
}
