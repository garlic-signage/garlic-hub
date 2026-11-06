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

namespace App\Modules\Templates\Services;

use App\Shared\Acl\AbstractAclValidator;
use App\Shared\Acl\AclHelper;
use App\Framework\Exceptions\CoreException;
use Doctrine\DBAL\Exception;

/**
 * AclValidator class is responsible for handling access control validation
 * for template-related operations.
 */
class AclValidator extends AbstractAclValidator
{
	protected const string UNIT_NAME = 'template';
	/** a global template may be used by everybody, in every company */
	public const string VISIBILITY_GLOBAL = 'global';

	public function __construct(AclHelper $aclHelper)
	{
		parent::__construct('templates', $aclHelper);
	}

    /**
     * @throws CoreException
     * @throws Exception
     */
    public function canCreate(int $UID): bool
	{
		return $this->isSimpleAdmin($UID);
	}

}