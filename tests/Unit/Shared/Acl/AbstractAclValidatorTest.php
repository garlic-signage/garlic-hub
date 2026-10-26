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

namespace Tests\Unit\Shared\Acl;

use App\Framework\Config\Config;
use App\Framework\Exceptions\FrameworkException;
use App\Shared\Acl\AbstractAclValidator;
use App\Shared\Acl\AclHelper;
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ConcreteAclValidator extends AbstractAclValidator
{
}

/**
 * What a user may do in a module, from the rights of the user (bits per module) and, in the editions with companies,
 * from the company of the unit. The rights are the ones of the module 'testModule': 1 viewer, 2 editor, 4 sub admin, 8 module admin.
 */
class AbstractAclValidatorTest extends TestCase
{
	private const int VIEWER = 1;
	private const int EDITOR = 2;
	private const int SUBADMIN = 4;
	private const int MODULEADMIN = 8;

	private function validator(int $acl, string $edition = Config::PLATFORM_EDITION_EDGE, string $module = 'testModule'): ConcreteAclValidator
	{
		$config = $this->createStub(Config::class);
		$config->method('getEdition')->willReturn($edition);
		$config->method('getConfigValue')->willReturnMap([
			['viewer', 'testModule', 'GlobalACLs', (string) self::VIEWER],
			['editor', 'testModule', 'GlobalACLs', (string) self::EDITOR],
			['subadmin', 'testModule', 'GlobalACLs', (string) self::SUBADMIN],
			['moduleadmin', 'testModule', 'GlobalACLs', (string) self::MODULEADMIN],
		]);
		// the user has the bits for the module of the validator, the company 5 as sub admin and nothing in other modules
		$user = new UserEntity([], [], [], [], [['module' => $module, 'acl' => $acl], ['module' => 'other', 'acl' => 15]], [['testModule_subadmin' => 5]]);
		$users = $this->createStub(UsersService::class);
		$users->method('getUserById')->willReturn($user);

		return new ConcreteAclValidator('testModule', new AclHelper($users, $config));
	}

	/**
	 * @return array<string,array{int,list<string>}> the bits of the user and the roles he has
	 */
	public static function roles(): array
	{
		return [
			'nothing'            => [0, []],
			'viewer'             => [self::VIEWER, ['viewer']],
			'editor'             => [self::EDITOR, ['editor']],
			'sub admin'          => [self::SUBADMIN, ['subadmin', 'simple']],
			'module admin'       => [self::MODULEADMIN, ['moduleadmin', 'simple']],
			'editor and viewer'  => [self::EDITOR | self::VIEWER, ['editor', 'viewer']],
			'all'                => [15, ['viewer', 'editor', 'subadmin', 'moduleadmin', 'simple']],
			'a bit of nobody'    => [16, []],
		];
	}

	/**
	 * @param list<string> $roles
	 */
	#[Group('units')]
	#[DataProvider('roles')]
	public function testTheRolesFollowTheBitsOfTheModule(int $acl, array $roles): void
	{
		$validator = $this->validator($acl);

		static::assertSame(in_array('moduleadmin', $roles, true), $validator->isModuleAdmin(1));
		static::assertSame(in_array('subadmin', $roles, true), $validator->isSubAdmin(1));
		static::assertSame(in_array('editor', $roles, true), $validator->isEditor(1));
		static::assertSame(in_array('viewer', $roles, true), $validator->isViewer(1));
		static::assertSame(in_array('simple', $roles, true), $validator->isSimpleAdmin(1));
	}

	#[Group('units')]
	public function testTheRightsOfAnotherModuleDoNotCount(): void
	{
		$validator = $this->validator(15, module: 'other');

		static::assertFalse($validator->isModuleAdmin(1));
		static::assertFalse($validator->isSimpleAdmin(1));
	}

	#[Group('units')]
	public function testTheNameOfTheModuleAndTheConfigAreThoseOfTheValidator(): void
	{
		$validator = $this->validator(0);

		static::assertSame('testModule', $validator->getModuleName());
		static::assertSame(Config::PLATFORM_EDITION_EDGE, $validator->getConfig()->getEdition());
	}

	#[Group('units')]
	public function testAModuleAdminMayAdministrateEveryUnitInEveryEdition(): void
	{
		foreach ([Config::PLATFORM_EDITION_EDGE, Config::PLATFORM_EDITION_CORE, Config::PLATFORM_EDITION_ENTERPRISE] as $edition)
			static::assertTrue($this->validator(self::MODULEADMIN, $edition)->isAdmin(1, ['UID' => 9]), $edition);
	}

	#[Group('units')]
	public function testInTheEdgeEditionOnlyTheModuleAdminAdministrates(): void
	{
		$validator = $this->validator(self::SUBADMIN);

		static::assertFalse($validator->isAdmin(1, ['UID' => 9, 'company_id' => 5]));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function editionsWithCompanies(): array
	{
		return ['core' => [Config::PLATFORM_EDITION_CORE], 'enterprise' => [Config::PLATFORM_EDITION_ENTERPRISE]];
	}

	#[Group('units')]
	#[DataProvider('editionsWithCompanies')]
	public function testASubAdminAdministratesTheUnitsOfHisCompany(string $edition): void
	{
		$validator = $this->validator(self::SUBADMIN, $edition);

		static::assertTrue($validator->isAdmin(1, ['UID' => 9, 'company_id' => 5]));
		static::assertTrue($validator->isSubAdminWithAccessOnCompany(1, 5));
	}

	#[Group('units')]
	#[DataProvider('editionsWithCompanies')]
	public function testASubAdminDoesNotAdministrateTheUnitsOfAnotherCompany(string $edition): void
	{
		$validator = $this->validator(self::SUBADMIN, $edition);

		static::assertFalse($validator->isAdmin(1, ['UID' => 9, 'company_id' => 6]));
		static::assertFalse($validator->isSubAdminWithAccessOnCompany(1, 6));
	}

	#[Group('units')]
	#[DataProvider('editionsWithCompanies')]
	public function testWhoIsNoSubAdminAdministratesNoCompany(string $edition): void
	{
		$validator = $this->validator(self::EDITOR | self::VIEWER, $edition);

		static::assertFalse($validator->isAdmin(1, ['UID' => 9, 'company_id' => 5]));
		static::assertFalse($validator->isSubAdminWithAccessOnCompany(1, 5));
	}

	/**
	 * @return array<string,array{array<string,int>}> the unit data without company or user
	 */
	public static function incompleteUnits(): array
	{
		return ['no company' => [['UID' => 9]], 'no user' => [['company_id' => 5]], 'nothing' => [[]]];
	}

	/**
	 * @param array<string,int> $unit
	 */
	#[Group('units')]
	#[DataProvider('incompleteUnits')]
	public function testAUnitWithoutCompanyOrUserCanNotBeJudgedInTheEditionsWithCompanies(array $unit): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Missing company id or UID in unit data.');

		/** @phpstan-ignore argument.type */
		$this->validator(self::SUBADMIN, Config::PLATFORM_EDITION_CORE)->isAdmin(1, $unit);
	}
}
