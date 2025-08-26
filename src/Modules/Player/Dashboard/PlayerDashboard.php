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

namespace App\Modules\Player\Dashboard;

use App\Framework\Core\Translate\Translator;
use App\Framework\Dashboards\DashboardInterface;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Represents a dashboard specifically for player-related information.
 * Implements the DashboardInterface.
 */
readonly class PlayerDashboard implements DashboardInterface
{
	private PlayerService $playerService;
	private Translator $translator;

	public function __construct(PlayerService $playerService, Translator $translator)
	{
		$this->playerService = $playerService;
		$this->translator = $translator;
	}

	public function getId(): string
	{
		return 'player';
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function getTitle(): string
	{
		return $this->translator->translate('dashboard', 'player');
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function renderContent(): string
	{
		$ar = $this->playerService->findAllForDashboard();

		return '<ul>
	<li><strong>'.$this->translator->translate('count_active', 'player').':</strong><span>'.$ar['active'].'</span></li>
	<li><strong>'.$this->translator->translate('count_pending', 'player').':</strong><span>'.$ar['pending'].'</span></li>
	<li><strong>'.$this->translator->translate('count_inactive', 'player').':</strong><span>'.$ar['inactive'].'</span></li>
</ul>';
	}
}