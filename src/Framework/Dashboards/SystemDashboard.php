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

use App\Framework\System\AppVersion;
use App\Framework\System\SystemStats;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Closure;

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
	private AppVersion $appVersion;
	/** @var Closure():bool the ACL lives outside the framework, the factory gives the check */
	private Closure $isSystemAdmin;

	/**
	 * @param Closure():bool $isSystemAdmin
	 */
	public function __construct(SystemStats $systemStats, Translator $translator, AppVersion $appVersion, Closure $isSystemAdmin)
	{
		$this->systemStats   = $systemStats;
		$this->translator    = $translator;
		$this->appVersion    = $appVersion;
		$this->isSystemAdmin = $isSystemAdmin;
	}

	public function getId(): string
	{
		return 'system';
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function getTitle(): string
	{
		return $this->translator->translate('system_dashboard', 'main');
	}

	/**
	 * Only the system admin sees the box: the load of the server and the version are nothing for the users,
	 * the version tells an attacker what runs. Without content the aggregator leaves the box out.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function renderContent(): string
	{
		if (!($this->isSystemAdmin)())
			return '';

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
'.$this->renderVersion().'</ul>';
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function renderVersion(): string
	{
		$version = $this->appVersion->getVersion();
		if ($version === '')
			return '';

		return "\t<li><strong>".$this->translator->translate('version', 'main').':</strong><span>'.htmlspecialchars($version)."</span></li>\n";
	}
}