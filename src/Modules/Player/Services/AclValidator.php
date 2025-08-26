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

namespace App\Modules\Player\Services;

use App\Framework\Core\Acl\AbstractAclValidator;
use App\Framework\Core\Acl\AclHelper;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;

/**
 * AclValidator class is responsible for handling access control validation
 * for player-related operations.
 */
class AclValidator extends AbstractAclValidator
{

	public function __construct(AclHelper $aclHelper)
	{
		parent::__construct('player', $aclHelper);
	}

	/**
	 * @param int $UID
	 * @param array{UID: int, company_id: int, player_id: int, ...} $player
	 * @return bool
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function isPlayerEditable(int $UID, array $player): bool
	{
		if ($this->isAllowedToDeletePlayer($UID, $player))
			return true;

		if($this->isEditorWithAccessOnUnit($UID, $player['player_id']))
			return true;

		return false;
	}

	/**
	 * @param array{UID: int, company_id: int, player_id:int, ...} $player
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function isAllowedToDeletePlayer(int $UID, array $player): bool
	{
		return $this->isAdmin($UID, $player);
	}

}