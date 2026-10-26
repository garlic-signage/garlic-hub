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
use App\Shared\Acl\AclHelper;
use Doctrine\DBAL\Exception;

/**
 * Menu of the logged-in user: own account, logout and for admins the administration links.
 */
readonly class UserMenuBuilder
{
	public function __construct(private Session $session, private Translator $translator, private AclHelper $aclHelper)
	{
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function build(): array
	{
		$user = $this->session->get('user');
		if (!is_array($user) || $user === [])
			return [];

		return [[
			'LANG_LOGIN_AS'       => $this->translator->translate('logged_in_as', 'menu'),
			'USERNAME'            => $user['username'] ?? '',
			'has_user_access'     => $this->buildAdminMenuPoints((int) ($user['UID'] ?? 0)),
			'LANG_MANAGE_ACCOUNT' => $this->translator->translate('my_account', 'menu'),
			'LANG_LOGOUT'         => $this->translator->translate('logout', 'menu')
		]];
	}

	/**
	 * @return list<array{LINK_USER_ACCESS: string, LANG_USER_ACCESS: string}>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	private function buildAdminMenuPoints(int $UID): array
	{
		$points = [];
		if ($this->isAdminOf($UID, 'users'))
			$points[] = ['LINK_USER_ACCESS' => '/users', 'LANG_USER_ACCESS' => $this->translator->translate('management', 'users')];

		if ($this->isAdminOf($UID, 'templates'))
			$points[] = ['LINK_USER_ACCESS' => '/templates', 'LANG_USER_ACCESS' => $this->translator->translate('overview', 'templates')];

		return $points;
	}

	/**
	 * The same rights which open the list of the module.
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	private function isAdminOf(int $UID, string $module): bool
	{
		return $this->aclHelper->isModuleAdmin($UID, $module) || $this->aclHelper->isSubAdmin($UID, $module);
	}
}
