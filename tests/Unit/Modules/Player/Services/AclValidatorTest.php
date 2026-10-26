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

namespace Tests\Unit\Modules\Player\Services;

use App\Framework\Config\Config;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Player\Services\AclValidator;
use App\Shared\Acl\AclHelper;
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * Who may change or delete a player. The AclHelper and the Config are the real ones, the user is a stub with
 * the global rights (acl) and the rights on single players or companies (vip) as the database holds them.
 */
class AclValidatorTest extends TestCase
{
	private const int VIEWER = 1;
	private const int EDITOR = 2;
	private const int SUBADMIN = 4;
	private const int MODULEADMIN = 8;
	private const int ME = 5;

	private UsersService&Stub $userService;
	/** @var list<array{module:string, acl:int}> */
	private array $acl = [];
	/** @var list<array<string,int>> */
	private array $vip = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->userService = $this->createStub(UsersService::class);
		$this->userService->method('getUserById')->willReturnCallback(fn(int $UID): UserEntity => $this->user($UID));
	}

	private function user(int $UID): UserEntity
	{
		return new UserEntity(['UID' => $UID], [], [], [], $this->acl, $this->vip);
	}

	private function validator(string $edition = Config::PLATFORM_EDITION_EDGE): AclValidator
	{
		$config = new Config(
			new RecordingConfigLoader(['player' => ['GlobalACLs' => ['viewer' => (string) self::VIEWER, 'editor' => (string) self::EDITOR, 'subadmin' => (string) self::SUBADMIN, 'moduleadmin' => (string) self::MODULEADMIN]]]),
			[],
			['APP_PLATFORM_EDITION' => $edition]
		);

		return new AclValidator(new AclHelper($this->userService, $config));
	}

	/**
	 * @param array<string,int> $changes
	 * @return array{UID: int, company_id: int, player_id: int}
	 */
	private function player(array $changes = []): array
	{
		/** @var array{UID: int, company_id: int, player_id: int} $player */
		$player = ['player_id' => 20, 'UID' => 9, 'company_id' => 4, ...$changes];

		return $player;
	}

	#[Group('units')]
	public function testTheModuleIsPlayer(): void
	{
		static::assertSame('player', $this->validator()->getModuleName());
	}

	#[Group('units')]
	public function testAUserWithoutAnyRightMayNothing(): void
	{
		static::assertFalse($this->validator()->isPlayerEditable(self::ME, $this->player()));
		static::assertFalse($this->validator()->isAllowedToDeletePlayer(self::ME, $this->player()));
	}

	/**
	 * The owner has no right because of owning: in the edge edition only the module admin changes players.
	 */
	#[Group('units')]
	public function testTheOwnerOfAPlayerHasNoRightBecauseOfOwningIt(): void
	{
		static::assertFalse($this->validator()->isPlayerEditable(self::ME, $this->player(['UID' => self::ME])));
	}

	#[Group('units')]
	public function testTheModuleAdminMayChangeAndDeleteEveryPlayer(): void
	{
		$this->acl = [['module' => 'player', 'acl' => self::MODULEADMIN]];

		static::assertTrue($this->validator()->isPlayerEditable(self::ME, $this->player()));
		static::assertTrue($this->validator()->isAllowedToDeletePlayer(self::ME, $this->player()));
		static::assertTrue($this->validator(Config::PLATFORM_EDITION_ENTERPRISE)->isAllowedToDeletePlayer(self::ME, $this->player(['company_id' => 99])));
	}

	#[Group('units')]
	public function testTheAdminOfAnotherModuleIsNoAdminHere(): void
	{
		$this->acl = [['module' => 'playlists', 'acl' => self::MODULEADMIN]];

		static::assertFalse($this->validator()->isPlayerEditable(self::ME, $this->player()));
	}

	/**
	 * @return array<string,array{string,int,bool}> the edition, the company of the right, the result
	 */
	public static function subAdmins(): array
	{
		return [
			'edge, own company'          => [Config::PLATFORM_EDITION_EDGE, 4, false],
			'enterprise, own company'    => [Config::PLATFORM_EDITION_ENTERPRISE, 4, true],
			'enterprise, other company'  => [Config::PLATFORM_EDITION_ENTERPRISE, 5, false],
		];
	}

	/**
	 * The edge edition has no sub admins, in the enterprise edition one is admin of the players of his company.
	 */
	#[Group('units')]
	#[DataProvider('subAdmins')]
	public function testTheSubAdminIsAdminOfHisCompanyInTheEnterpriseEditionOnly(string $edition, int $company, bool $expected): void
	{
		$this->acl = [['module' => 'player', 'acl' => self::SUBADMIN]];
		$this->vip = [['player_subadmin' => $company]];

		static::assertSame($expected, $this->validator($edition)->isPlayerEditable(self::ME, $this->player()));
		static::assertSame($expected, $this->validator($edition)->isAllowedToDeletePlayer(self::ME, $this->player()));
	}

	#[Group('units')]
	public function testAPlayerWithoutCompanyOrOwnerCanNotBeJudgedInTheEnterpriseEdition(): void
	{
		$this->expectException(FrameworkException::class);

		// @phpstan-ignore argument.type
		$this->validator(Config::PLATFORM_EDITION_ENTERPRISE)->isPlayerEditable(self::ME, ['player_id' => 20]);
	}

	/**
	 * @return array<string,array{list<array{module:string,acl:int}>,list<array<string,int>>,bool,bool}> the rights, the result for editing and for deleting
	 */
	public static function editors(): array
	{
		return [
			'editor of the player'          => [[['module' => 'player', 'acl' => self::EDITOR]], [['player_editor' => 20]], true, false],
			'editor of another player'      => [[['module' => 'player', 'acl' => self::EDITOR]], [['player_editor' => 21]], false, false],
			'editor right on no player'     => [[['module' => 'player', 'acl' => self::EDITOR]], [], false, false],
			'right on the player, no editor' => [[], [['player_editor' => 20]], false, false],
			'viewer of the player'          => [[['module' => 'player', 'acl' => self::VIEWER]], [['player_viewer' => 20]], false, false],
		];
	}

	/**
	 * An editor of a player changes it, but does not delete it.
	 *
	 * @param list<array{module:string,acl:int}> $acl
	 * @param list<array<string,int>> $vip
	 */
	#[Group('units')]
	#[DataProvider('editors')]
	public function testAnEditorChangesHisPlayerButDoesNotDeleteIt(array $acl, array $vip, bool $editable, bool $deletable): void
	{
		$this->acl = $acl;
		$this->vip = $vip;

		static::assertSame($editable, $this->validator()->isPlayerEditable(self::ME, $this->player()));
		static::assertSame($deletable, $this->validator()->isAllowedToDeletePlayer(self::ME, $this->player()));
	}
}
