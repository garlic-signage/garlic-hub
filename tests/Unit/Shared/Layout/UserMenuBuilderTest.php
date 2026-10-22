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

namespace Tests\Unit\Shared\Layout;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Shared\Acl\AclHelper;
use App\Shared\Layout\UserMenuBuilder;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UserMenuBuilderTest extends TestCase
{
	private Session&Stub $sessionStub;
	private AclHelper&MockObject $aclHelperMock;
	private UserMenuBuilder $builder;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionStub   = $this->createStub(Session::class);
		$this->aclHelperMock = $this->createMock(AclHelper::class);
		$translatorStub      = $this->createStub(Translator::class);
		$translatorStub->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);

		$this->builder = new UserMenuBuilder($this->sessionStub, $translatorStub, $this->aclHelperMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildWithoutUserIsEmpty(): void
	{
		$this->sessionStub->method('get')->willReturn(null);
		$this->aclHelperMock->expects($this->never())->method($this->anything());

		static::assertSame([], $this->builder->build());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildForUserWithoutAdminRights(): void
	{
		$this->sessionStub->method('get')->willReturn(['UID' => 7, 'username' => 'heidi']);
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with(7, 'users')->willReturn(false);
		$this->aclHelperMock->expects($this->once())->method('isSubAdmin')->with(7, 'users')->willReturn(false);

		static::assertSame([[
			'LANG_LOGIN_AS'       => 'menu.logged_in_as',
			'USERNAME'            => 'heidi',
			'has_user_access'     => [],
			'LANG_MANAGE_ACCOUNT' => 'menu.my_account',
			'LANG_LOGOUT'         => 'menu.logout'
		]], $this->builder->build());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildForAdminAddsAdministrationLinks(): void
	{
		$this->sessionStub->method('get')->willReturn(['UID' => 1, 'username' => 'admin']);
		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')->with(1, 'users')->willReturn(true);
		$this->aclHelperMock->expects($this->never())->method('isSubAdmin');

		$menu = $this->builder->build();

		static::assertSame([
			['LINK_USER_ACCESS' => '/users', 'LANG_USER_ACCESS' => 'users.management'],
			['LINK_USER_ACCESS' => '/templates', 'LANG_USER_ACCESS' => 'templates.overview']
		], $menu[0]['has_user_access']);
	}
}
