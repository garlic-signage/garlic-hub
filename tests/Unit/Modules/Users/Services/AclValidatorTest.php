<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


namespace Tests\Unit\Modules\Users\Services;

use App\Framework\Core\Acl\AclHelper;
use App\Modules\Users\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AclValidatorTest extends TestCase
{
	private AclValidator $aclValidator;
	private AclHelper&MockObject $aclHelperMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->aclHelperMock = $this->createMock(AclHelper::class);

		$this->aclValidator    = new AclValidator($this->aclHelperMock);
	}

	/**
	 * @return void
	 */
	#[Group('units')]
	public function testcheckModuleName(): void
	{
		$this->assertSame('users', $this->aclValidator->getModuleName());
	}
}
