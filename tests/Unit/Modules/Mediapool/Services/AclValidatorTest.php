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

namespace Tests\Unit\Modules\Mediapool\Services;

use App\Framework\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Services\AclValidator;
use App\Shared\Acl\AclHelper;
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * The rights of a user on a directory. The AclHelper and the Config are the real ones: the user is a stub
 * with the global rights (acl) and the rights on single directories or companies (vip) as the database holds them.
 * The same through the whole application is tested in tests/Integration/Modules/Mediapool/Services/NodeAccessTest.
 */
class AclValidatorTest extends TestCase
{
	private const int VIEWER = 1;
	private const int EDITOR = 2;
	private const int SUBADMIN = 4;
	private const int MODULEADMIN = 8;

	private const int ME = 5;

	private UsersService&Stub $userService;
	private AclValidator $validator;
	/** @var list<array{module:string, acl:int}> */
	private array $acl = [];
	/** @var list<array<string,int>> */
	private array $vip = [];

	protected function setUp(): void
	{
		parent::setUp();
		$config = new Config(new RecordingConfigLoader([
			'mediapool' => ['GlobalACLs' => ['viewer' => (string) self::VIEWER, 'editor' => (string) self::EDITOR, 'subadmin' => (string) self::SUBADMIN, 'moduleadmin' => (string) self::MODULEADMIN]]
		]));
		$this->userService = $this->createStub(UsersService::class);
		$this->userService->method('getUserById')->willReturnCallback(
			fn(int $UID): UserEntity => $this->user($UID)
		);
		$this->validator = new AclValidator(new AclHelper($this->userService, $config));
	}

	/**
	 * The database gives the rights as lists, the declaration of the entity says array<string,mixed>.
	 */
	private function user(int $UID): UserEntity
	{
		return new UserEntity(['UID' => $UID], [], [], [], $this->acl, $this->vip);
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array{UID:int, company_id:int, node_id:int, visibility:int, parent_id:int}
	 */
	private function directory(array $changes = []): array
	{
		/** @var array{UID:int, company_id:int, node_id:int, visibility:int, parent_id:int} $directory */
		$directory = array_merge(['UID' => 9, 'company_id' => 4, 'node_id' => 20, 'visibility' => 0, 'parent_id' => 12], $changes);

		return $directory;
	}

	/**
	 * @param array{create:bool, read:bool, edit:bool, share:string} $expected
	 * @param array<string,mixed> $directory
	 */
	private function assertPermissions(array $expected, array $directory): void
	{
		static::assertSame($expected, $this->validator->checkDirectoryPermissions(self::ME, $directory));
	}

	#[Group('units')]
	public function testTheModuleIsMediapool(): void
	{
		static::assertSame('mediapool', $this->validator->getModuleName());
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function incompleteDirectories(): array
	{
		return [
			'UID'        => [['UID' => null]],
			'company'    => [['company_id' => null]],
			'node'       => [['node_id' => null]],
			'parent'     => [['parent_id' => null]],
			'visibility' => [['visibility' => null]],
		];
	}

	/**
	 * A directory without one of these values can not be judged: no right is better than a wrong one.
	 *
	 * @param array<string,mixed> $remove
	 */
	#[Group('units')]
	#[DataProvider('incompleteDirectories')]
	public function testADirectoryWithMissingValuesIsAnError(array $remove): void
	{
		$directory = $this->directory();
		foreach (array_keys($remove) as $key)
			unset($directory[$key]);
		$this->acl = [['module' => 'mediapool', 'acl' => self::MODULEADMIN]];

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Missing important values');

		$this->validator->checkDirectoryPermissions(self::ME, $directory);
	}

	#[Group('units')]
	public function testAUserWithoutAnyRightHasNone(): void
	{
		$this->assertPermissions(['create' => false, 'read' => false, 'edit' => false, 'share' => ''], $this->directory());
	}

	#[Group('units')]
	public function testTheModuleAdminMayDoEverythingEverywhere(): void
	{
		$this->acl = [['module' => 'mediapool', 'acl' => self::MODULEADMIN]];

		$all = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->assertPermissions($all, $this->directory());
		$this->assertPermissions($all, $this->directory(['parent_id' => 0]));
		$this->assertPermissions($all, $this->directory(['visibility' => 0, 'UID' => 99, 'company_id' => 99]));
	}

	/**
	 * The admin of another module has nothing to say here.
	 */
	#[Group('units')]
	public function testTheAdminOfAnotherModuleIsNoAdminHere(): void
	{
		$this->acl = [['module' => 'playlists', 'acl' => self::MODULEADMIN]];

		$this->assertPermissions(['create' => false, 'read' => false, 'edit' => false, 'share' => ''], $this->directory());
	}

	#[Group('units')]
	public function testTheOwnerMayDoEverythingInHisDirectoryAlsoOnTheRootLevel(): void
	{
		$all = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];

		$this->assertPermissions($all, $this->directory(['UID' => self::ME]));
		$this->assertPermissions($all, $this->directory(['UID' => self::ME, 'parent_id' => 0]));
	}

	/**
	 * @return array<string,array{list<array{module:string,acl:int}>,list<array<string,int>>,array<string,mixed>,array{create:bool,read:bool,edit:bool,share:string}}>
	 */
	public static function roleProvider(): array
	{
		return [
			'subadmin of the company' => [
				[['module' => 'mediapool', 'acl' => self::SUBADMIN]], [['mediapool_subadmin' => 4]], [],
				['create' => true, 'read' => true, 'edit' => true, 'share' => 'company'],
			],
			'subadmin of another company' => [
				[['module' => 'mediapool', 'acl' => self::SUBADMIN]], [['mediapool_subadmin' => 5]], [],
				['create' => false, 'read' => false, 'edit' => false, 'share' => ''],
			],
			'subadmin of the company, on the root level' => [
				[['module' => 'mediapool', 'acl' => self::SUBADMIN]], [['mediapool_subadmin' => 4]], ['parent_id' => 0],
				['create' => true, 'read' => true, 'edit' => false, 'share' => 'company'],
			],
			'right as subadmin without the right on a company' => [
				[['module' => 'mediapool', 'acl' => self::SUBADMIN]], [], [],
				['create' => false, 'read' => false, 'edit' => false, 'share' => ''],
			],
			'company right without the global right' => [
				[], [['mediapool_subadmin' => 4]], [],
				['create' => false, 'read' => false, 'edit' => false, 'share' => ''],
			],
			'editor of the directory' => [
				[['module' => 'mediapool', 'acl' => self::EDITOR]], [['mediapool_editor' => 20]], [],
				['create' => true, 'read' => true, 'edit' => false, 'share' => ''],
			],
			'editor of another directory' => [
				[['module' => 'mediapool', 'acl' => self::EDITOR]], [['mediapool_editor' => 21]], [],
				['create' => false, 'read' => false, 'edit' => false, 'share' => ''],
			],
			'viewer of the directory' => [
				[['module' => 'mediapool', 'acl' => self::VIEWER]], [['mediapool_viewer' => 20]], [],
				['create' => false, 'read' => true, 'edit' => false, 'share' => ''],
			],
			'viewer of another directory' => [
				[['module' => 'mediapool', 'acl' => self::VIEWER]], [['mediapool_viewer' => 21]], [],
				['create' => false, 'read' => false, 'edit' => false, 'share' => ''],
			],
			'viewer right without the right on a directory' => [
				[['module' => 'mediapool', 'acl' => self::VIEWER]], [], [],
				['create' => false, 'read' => false, 'edit' => false, 'share' => ''],
			],
			'editor and viewer of the directory' => [
				[['module' => 'mediapool', 'acl' => self::EDITOR | self::VIEWER]], [['mediapool_editor' => 20], ['mediapool_viewer' => 20]], [],
				['create' => true, 'read' => true, 'edit' => false, 'share' => ''],
			],
			'subadmin who is editor of the directory loses the edit right' => [
				[['module' => 'mediapool', 'acl' => self::SUBADMIN | self::EDITOR]], [['mediapool_subadmin' => 4], ['mediapool_editor' => 20]], [],
				['create' => true, 'read' => true, 'edit' => false, 'share' => ''],
			],
		];
	}

	/**
	 * @param list<array{module:string,acl:int}> $acl
	 * @param list<array<string,int>> $vip
	 * @param array<string,mixed> $directoryChanges
	 * @param array{create:bool,read:bool,edit:bool,share:string} $expected
	 */
	#[Group('units')]
	#[DataProvider('roleProvider')]
	public function testTheRightsOfTheRoles(array $acl, array $vip, array $directoryChanges, array $expected): void
	{
		$this->acl = $acl;
		$this->vip = $vip;

		$this->assertPermissions($expected, $this->directory($directoryChanges));
	}

	/**
	 * @return array<string,array{int,int,bool}>
	 */
	public static function visibilityProvider(): array
	{
		return [
			'private'        => [0, 12, false],
			'bit 1 only'     => [1, 12, false],
			'public'         => [AclValidator::VISIBILITY_PUBLIC, 12, true],
			'public + bit 1' => [AclValidator::VISIBILITY_PUBLIC | 1, 12, true],
			'public root'    => [AclValidator::VISIBILITY_PUBLIC, 0, true],
		];
	}

	/**
	 * A public directory can be read by everybody, but nobody gets more than that from it.
	 */
	#[Group('units')]
	#[DataProvider('visibilityProvider')]
	public function testOnlyThePublicBitMakesADirectoryReadable(int $visibility, int $parent, bool $readable): void
	{
		$this->assertPermissions(
			['create' => false, 'read' => $readable, 'edit' => false, 'share' => ''],
			$this->directory(['visibility' => $visibility, 'parent_id' => $parent])
		);
	}

	/**
	 * The root directories can be changed by the module admin only, whatever the role on them is.
	 */
	#[Group('units')]
	public function testNobodyButTheAdminEditsARootDirectory(): void
	{
		$this->acl = [['module' => 'mediapool', 'acl' => self::SUBADMIN | self::EDITOR | self::VIEWER]];
		$this->vip = [['mediapool_subadmin' => 4], ['mediapool_editor' => 20], ['mediapool_viewer' => 20]];

		$permissions = $this->validator->checkDirectoryPermissions(self::ME, $this->directory(['parent_id' => 0]));

		static::assertFalse($permissions['edit']);
	}
}
