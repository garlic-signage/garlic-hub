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

namespace App\Modules\Player\Helper\Datatable;

use App\Framework\Database\Where;
use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Database\Datatable\FilterDefinition;
use App\Modules\Player\Enums\PlayerActivity;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

readonly class PlayerFilterDefinition implements FilterDefinition
{
	private const string TABLE = 'player';

	public function __construct(private FilterConditions $conditions, private AbstractPlatform $platform)
	{
	}

	public function table(): string
	{
		return self::TABLE;
	}

	public function joins(): array
	{
		return [
			'user_main' => 'user_main.UID = ' . self::TABLE . '.UID',
			'playlists' => 'playlists.playlist_id = ' . self::TABLE . '.playlist_id'
		];
	}

	public function userJoins(): array
	{
		return ['playlists' => 'playlists.playlist_id = ' . self::TABLE . '.playlist_id'];
	}

	public function selects(): array
	{
		return ['player_id',
				self::TABLE.'.playlist_id',
				'playlist_name', 'is_intranet',
				'firmware', self::TABLE.'.status', 'model',
				'commands',	'reports',
				self::TABLE.'.last_access',
				'refresh', 'player_name, '.self::TABLE.'.UID'
		];
	}

	public function selectsWithUser(): array
	{
		return array_merge($this->selects(), ['user_main.username', 'user_main.company_id']);
	}

	public function whereForFiltering(array $fields): array
	{
		$where = [];
		foreach ($fields as $key => $parameter)
		{
			switch ($key)
			{
				case 'activity':
					$activity = $parameter['value'] ?? '';
					if ($activity === PlayerActivity::ACTIVE->value)
						$where[self::TABLE.'.last_access'] = Where::raw(self::TABLE.'.last_access >= '.$this->activeSince());
					elseif ($activity === PlayerActivity::INACTIVE->value)
						$where[self::TABLE.'.last_access'] = Where::raw(self::TABLE.'.last_access < '.$this->activeSince());
					break;

				case 'playlist_id':
					if ((int) ($parameter['value'] ?? 0) > 0)
						$where[self::TABLE.'.'.$key] = Where::equals(self::TABLE.'.'.$key, (int) $parameter['value']);
					break;

				default:
					$where = array_merge($where, $this->conditions->forField(self::TABLE, $key, $parameter));
			}
		}
		return $where;
	}

	/**
	 * A player is active when it accessed the server within two refresh intervals.
	 * Same threshold as PlayerRepository::findAllForDashboard.
	 */
	private function activeSince(): string
	{
		if ($this->platform instanceof SQLitePlatform)
			return "DATETIME('now', '-' || (2 * ".self::TABLE.".refresh) || ' seconds')";

		return 'DATE_SUB(NOW(), INTERVAL (2 * '.self::TABLE.'.refresh) SECOND)';
	}
}
