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

namespace Tests\Unit\Modules\Users\Helper\Settings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Helper\Settings\Parameters;
use App\Modules\Users\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsParametersPolicyTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private SettingsParametersPolicy $policy;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$userSessionStub        = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(7);

		$this->policy = new SettingsParametersPolicy($this->parametersMock, $this->aclValidatorMock, $userSessionStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddCreateFormElementsForSimpleAdmin(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(7)->willReturn(true);
		$this->expectUserFormElements(true);

		$this->policy->addCreateFormElements();
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddCreateFormElementsWithoutRights(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(7)->willReturn(false);
		$this->expectUserFormElements(false);

		$this->policy->addCreateFormElements();
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddEditFormElementsForAdmin(): void
	{
		$user = ['UID' => 12, 'company_id' => 1];
		$this->aclValidatorMock->expects($this->once())->method('isAdmin')->with(7, $user)->willReturn(true);
		$this->expectUserFormElements(true);

		$this->policy->addEditFormElements($user);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddEditFormElementsWithoutRights(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isAdmin')->willReturn(false);
		$this->expectUserFormElements(false);

		$this->policy->addEditFormElements(['UID' => 12, 'company_id' => 1]);
	}

	private function expectUserFormElements(bool $added): void
	{
		foreach (['addUserName', 'addUserEmail', 'addUserStatus', 'addUserLocale'] as $method)
		{
			$this->parametersMock->expects($added ? $this->once() : $this->never())->method($method);
		}
	}
}
