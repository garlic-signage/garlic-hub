<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Mediapool\Services;


use App\Framework\Core\Acl\AbstractAclValidator;
use App\Framework\Core\Acl\AclHelper;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;

/**
 * AclValidator class provides functionality for validating access control
 * permissions on directories based on the user's role, ownership, and visibility settings.
 */
class AclValidator extends AbstractAclValidator
{
	public const int VISIBILITY_PUBLIC = 2;

	public function __construct(AclHelper $aclHelper)
	{
		parent::__construct('mediapool', $aclHelper);
	}

	/**
	 * @param array{UID?:int, node_id?:int, company_id?:int, visibility?:int, parent_id?:int, ...}$directory
	 * @return array{create:bool, read:bool, edit:bool, share:string}
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function checkDirectoryPermissions(int $UID, array $directory): array
	{
		if (!array_key_exists('UID', $directory) || !array_key_exists('company_id', $directory)
			|| !array_key_exists('node_id', $directory) || !array_key_exists('parent_id', $directory)
			|| !array_key_exists('visibility', $directory))
			throw new ModuleException($this->moduleName, 'Missing important values in media directory data struct.');

		$permissions = ['create' => false, 'read' => false, 'edit' => false, 'share' => ''];

		// Check for module admin or directory owner
		if ($this->isModuleAdmin($UID) || $directory['UID'] == $UID)
			return ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];

		// Edge Edition will not move further as there is not subadmin
		if ($this->isSubAdminWithAccessOnCompany($UID, $directory['company_id']))
			$permissions = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'company'];

		if ($this->isEditorWithAccessOnUnit($UID, $directory['node_id']))
			$permissions = ['create' => true, 'read' => true, 'edit' => false, 'share' => ''];

		if ($this->isViewerWithAccessOnUnit($UID, $directory['node_id']))
			$permissions['read'] = true;

		if (($directory['visibility'] & self::VISIBILITY_PUBLIC) > 0)
			$permissions['read'] = true;

		// Only moduleadmin can edit root directories
		// nno need to check on Moduleadmin, as this happens above with:
		// if ($this->isModuleAdmin($UID) || $directory['UID'] == $UID)
		// module admin will never reach this line
		if ($directory['parent_id'] === 0)
			$permissions['edit'] = false;

		return $permissions;
	}

}