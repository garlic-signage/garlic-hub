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

namespace App\Shared\Layout;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;

/**
 * Main navigation: the modules for logged-in users, otherwise the login link.
 * The entry whose URL the current path starts with is marked active.
 */
readonly class MainMenuBuilder
{
	public function __construct(private Session $session, private Translator $translator)
	{
	}

	/**
	 * @return list<array{URL: string, LANG_MENU_POINT: string, ACTIVE: string}>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function build(string $currentPath): array
	{
		if (!$this->session->exists('user'))
			return [$this->buildEntry('/login', $this->translator->translate('login', 'login'), $currentPath)];

		return [
			$this->buildEntry('/player', $this->translator->translate('player', 'menu'), $currentPath),
			$this->buildEntry('/playlists', $this->translator->translate('playlists', 'menu'), $currentPath),
			$this->buildEntry('/mediapool', $this->translator->translate('mediapool', 'menu'), $currentPath)
		];
	}

	/**
	 * @return array{URL: string, LANG_MENU_POINT: string, ACTIVE: string}
	 */
	private function buildEntry(string $url, string $menuPoint, string $currentPath): array
	{
		$isActive = $currentPath === $url || str_starts_with($currentPath, $url . '/');

		return ['URL' => $url, 'LANG_MENU_POINT' => $menuPoint, 'ACTIVE' => $isActive ? 'active' : ''];
	}
}
