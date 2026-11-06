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
use App\Framework\Localization\Translate\Translator;
use App\Shared\Acl\AclHelper;
use Doctrine\DBAL\Exception;

/**
 * The areas of the administration page, only those whose rights the user has. Without one the user menu has
 * no administration. New areas come here, not into the user menu.
 */
readonly class AdministrationLinks
{
	public function __construct(private AclHelper $aclHelper, private Translator $translator)
	{}

	/**
	 * @return list<array{LINK:string, LANG_TITLE:string, LANG_TEXT:string}>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function build(int $UID): array
	{
		$links = [];
		if ($this->isAdminOf($UID, 'users'))
			$links[] = $this->link('/users', $this->translator->translate('management', 'users'), 'administration_users');

		// the providers belong to the company, a sub admin may not change them
		if ($this->aclHelper->isModuleAdmin($UID, 'users'))
			$links[] = $this->link('/users/sso', $this->translator->translate('sso_providers', 'users'), 'administration_sso');

		if ($this->isAdminOf($UID, 'templates'))
			$links[] = $this->link('/templates', $this->translator->translate('overview', 'templates'), 'administration_templates');

		if ($this->aclHelper->isModuleAdmin($UID, 'system'))
			$links[] = $this->link('/system/audit', $this->translator->translate('audit_log', 'system'), 'administration_audit');

		return $links;
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

	/**
	 * @return array{LINK:string, LANG_TITLE:string, LANG_TEXT:string}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function link(string $link, string $title, string $text): array
	{
		return ['LINK' => $link, 'LANG_TITLE' => $title, 'LANG_TEXT' => $this->translator->translate($text, 'system')];
	}
}
