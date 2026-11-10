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

namespace Tests\Unit\Modules\Users\Services;

use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\UserActions;
use App\Shared\Acl\AclGrantService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The entries of the context menu of a user in the list.
 */
class UserActionsTest extends TestCase
{
	/**
	 * The validator decides about the user with his UID and company: in the editions a sub admin needs both
	 * (AbstractAclValidator::isAdmin refuses unit data without the UID).
	 */
	#[Group('units')]
	public function testTheRightsAreAskedForTheUserWithHisUidAndCompany(): void
	{
		$acl = $this->createMock(AclValidator::class);
		$acl->expects($this->once())->method('isAdmin')->with(5, ['UID' => 7, 'company_id' => 3])->willReturn(true);
		$acl->method('isModuleAdmin')->willReturn(false);

		$actions = new UserActions($acl, $this->createStub(AclGrantService::class))->determine(5, 7, 3, 3);

		static::assertSame(['can_edit' => true, 'can_rights' => false, 'can_delete' => false], $actions);
	}
}
