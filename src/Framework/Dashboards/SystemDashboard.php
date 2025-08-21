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

namespace App\Framework\Dashboards;

use App\Framework\Core\SystemStats;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Works only in Linux
 * Represents a system dashboard that provides system statistics and localized content.
 *
 * Implements the DashboardInterface to provide an ID, title, and rendered content for the dashboard.
 */
readonly class SystemDashboard implements DashboardInterface
{
	private SystemStats $systemStats;
	private Translator $translator;

	public function __construct(SystemStats $systemStats, Translator $translator)
	{
		$this->systemStats = $systemStats;
		$this->translator = $translator;
	}

	public function getId(): string
	{
		return 'system';
	}


	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function getTitle(): string
	{
		return $this->translator->translate('system_dashboard', 'main');
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function renderContent(): string
	{
		$this->systemStats->determineSystemStats();
		$load = implode( ' | ', $this->systemStats->getLoadData());

		$ar = $this->systemStats->getRamStats();
		$memory = sprintf($this->translator->translate('memory_used', 'main'), $ar['used'], $ar['total']);

		$ar = $this->systemStats->getDiscInfo();
		$disc = sprintf($this->translator->translate('disc_used', 'main'), $ar['used'], $ar['size'], $ar['percent']);

		return '<ul>
	<li><strong>'.$this->translator->translate('system_load', 'main').':</strong><span>'.$load.'</span></li>
	<li><strong>'.$this->translator->translate('memory_usage', 'main').':</strong><span>'.$memory.'</span></li>
	<li><strong>'.$this->translator->translate('disc_usage', 'main').':</strong><span>'.$disc.'</span></li>
</ul>';

	}
}