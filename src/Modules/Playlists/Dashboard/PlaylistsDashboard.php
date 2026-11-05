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

namespace App\Modules\Playlists\Dashboard;

use App\Framework\Dashboards\DashboardInterface;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Playlists\Services\PlaylistRelease;
use Doctrine\DBAL\Exception;

/**
 * The playlists which wait for the release. Only for the module admin and only while the release is on,
 * otherwise the content is empty and the home page leaves the dashboard out.
 */
readonly class PlaylistsDashboard implements DashboardInterface
{
	public function __construct(private PlaylistRelease $playlistRelease, private Translator $translator) {}

	public function getId(): string
	{
		return 'playlists';
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function getTitle(): string
	{
		return $this->translator->translate('dashboard', 'playlists');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function renderContent(): string
	{
		$waiting = $this->playlistRelease->countWaiting();
		if ($waiting === null)
			return '';

		return '<ul>
	<li><strong>'.$this->translator->translate('count_release_ready', 'playlists').':</strong><span>'.$waiting.'</span></li>
</ul>';
	}
}
