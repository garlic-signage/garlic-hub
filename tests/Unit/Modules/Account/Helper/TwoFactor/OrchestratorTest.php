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

namespace Tests\Unit\Modules\Account\Helper\TwoFactor;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Modules\Account\Helper\TwoFactor\Orchestrator;
use App\Modules\Auth\LoginThrottle;
use App\Modules\Auth\UserSession;
use App\Shared\Audit\NullAuditLog;
use App\Shared\User\TwoFactor\TwoFactorService;
use App\Shared\User\UsersService;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArrayCache;
use Tests\Support\MemorySession;

/**
 * The tab of the second factor. The throttle is the real one with a cache in memory, the service of the second
 * factor a stub which knows the code 123456. The second factor with the database is tested in
 * tests/Integration/Shared/User/TwoFactor/TwoFactorServiceTest.
 */
class OrchestratorTest extends TestCase
{
	private const string RIGHT_CODE = '123456';
	private const array NEW_CODES   = ['aaaa-bbbb', 'cccc-dddd'];

	private MemorySession $session;
	private TwoFactorService&Stub $twoFactorServiceStub;
	private Config&Stub $configStub;
	private Orchestrator $orchestrator;
	/** @var array<string,mixed> */
	private array $server;

	protected function setUp(): void
	{
		parent::setUp();
		$this->server = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		$this->session = new MemorySession();
		$this->session->set('user', ['UID' => 7, 'username' => 'anna', 'locale' => 'de_DE']);

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(
			static fn(string $key, string $module): string => $key === 'two_factor_recovery_left' ? '%d codes left' : $module . '.' . $key
		);
		$csrfTokenStub = $this->createStub(CsrfToken::class);
		$csrfTokenStub->method('getToken')->willReturn('the_token');

		$this->twoFactorServiceStub = $this->createStub(TwoFactorService::class);
		$this->twoFactorServiceStub->method('createRecoveryCodes')->willReturnCallback(static fn(int $UID, string $code): array => $code === self::RIGHT_CODE ? self::NEW_CODES : []);
		$this->twoFactorServiceStub->method('disable')->willReturnCallback(static fn(int $UID, string $code): bool => $code === self::RIGHT_CODE);
		$this->twoFactorServiceStub->method('prepareSetup')->willReturn(['secret' => 'ABCDEFGHIJ', 'qr_code' => '<svg/>']);
		$this->twoFactorServiceStub->method('countRecoveryCodes')->willReturn(8);
		$this->configStub = $this->createStub(Config::class);
		$usersServiceStub = $this->createStub(UsersService::class);
		$usersServiceStub->method('endSessions')->willReturn('2026-11-10 19:00:00');

		$this->orchestrator = new Orchestrator(
			new UserSession($this->session),
			$this->twoFactorServiceStub,
			$this->session,
			$this->configStub,
			$translator,
			$csrfTokenStub,
			new LoginThrottle(new ArrayCache()),
			new NullAuditLog(),
			$usersServiceStub,
			$this->createStub(UserTokenService::class)
		);
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;
		parent::tearDown();
	}

	private function settings(bool $enabled, bool $required): void
	{
		$this->twoFactorServiceStub->method('isEnabled')->willReturn($enabled);
		$this->configStub->method('isSwitchOn')->willReturnMap([['required', 'users', 'TwoFactor', $required]]);
	}

	#[Group('units')]
	public function testTheTabOfAUserWithoutSecondFactorShowsTheSetup(): void
	{
		$this->settings(false, false);

		static::assertFalse($this->orchestrator->isRequired());
		static::assertSame([
			'CSRF_TOKEN'  => 'the_token',
			'LANG_CODE'   => 'account.two_factor_code',
			'is_required' => [],
			'new_codes'   => [],
			'setup'       => [
				'LANG_SETUP'  => 'account.two_factor_setup',
				'QR_CODE'     => '<svg/>',
				'LANG_SECRET' => 'account.two_factor_secret',
				'SECRET'      => 'ABCD EFGH IJ',
				'LANG_ENABLE' => 'account.two_factor_enable'
			],
			'enabled'     => []
		], $this->orchestrator->buildTab());
	}

	#[Group('units')]
	public function testTheTabSaysWhenTheAdminRequiresTheSecondFactor(): void
	{
		$this->settings(false, true);

		static::assertTrue($this->orchestrator->isRequired());
		static::assertSame(['LANG_REQUIRED' => 'account.two_factor_required'], $this->orchestrator->buildTab()['is_required']);
	}

	#[Group('units')]
	public function testTheTabOfAUserWithSecondFactor(): void
	{
		$this->settings(true, false);

		$tab = $this->orchestrator->buildTab();

		static::assertSame([], $tab['setup']);
		static::assertSame([], $tab['is_required']);
		static::assertSame([
			'LANG_ENABLED'            => 'account.two_factor_enabled',
			'LANG_RECOVERY_LEFT'      => '8 codes left',
			'LANG_NEW_RECOVERY_CODES' => 'account.two_factor_new_recovery_codes',
			'can_disable'             => ['LANG_DISABLE' => 'account.two_factor_disable']
		], $tab['enabled']);
	}

	#[Group('units')]
	public function testWhileItIsRequiredTheUserCanNotTurnItOff(): void
	{
		$this->settings(true, true);

		$tab = $this->orchestrator->buildTab();

		static::assertSame([], $tab['is_required'], 'He has it already.');
		static::assertSame([], $tab['enabled']['can_disable']);
		static::assertSame('two_factor_wrong_code', $this->orchestrator->disable(self::RIGHT_CODE));
	}

	#[Group('units')]
	public function testNewRecoveryCodesAreShownOnce(): void
	{
		$this->settings(true, false);

		static::assertSame('', $this->orchestrator->createRecoveryCodes(self::RIGHT_CODE));

		static::assertSame([
			'LANG_NEW_CODES' => 'account.two_factor_new_codes',
			'codes'          => [['CODE' => 'aaaa-bbbb'], ['CODE' => 'cccc-dddd']]
		], $this->orchestrator->buildTab()['new_codes']);
		static::assertSame([], $this->orchestrator->buildTab()['new_codes']);
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function noCodes(): array
	{
		return ['no array' => ['codes'], 'codes no array' => [['codes' => 'aaaa-bbbb']], 'no codes' => [['codes' => []]], 'other key' => [['other' => ['aaaa-bbbb']]]];
	}

	#[Group('units')]
	#[DataProvider('noCodes')]
	public function testAnythingElseInTheSessionIsNoCode(mixed $stored): void
	{
		$this->settings(true, false);
		$this->session->set('two_factor_new_codes', $stored);

		static::assertSame([], $this->orchestrator->buildTab()['new_codes']);
	}

	#[Group('units')]
	public function testACodeWhichIsNoTextIsEmpty(): void
	{
		$this->settings(true, false);
		$this->session->set('two_factor_new_codes', ['codes' => ['x' => 'aaaa-bbbb', 'y' => 5]]);

		static::assertSame([['CODE' => 'aaaa-bbbb'], ['CODE' => '']], $this->orchestrator->buildTab()['new_codes']['codes']);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function codeForms(): array
	{
		return ['new recovery codes' => ['createRecoveryCodes'], 'turn off' => ['disable']];
	}

	/**
	 * Wrong codes block the address: then the right code does not help anymore, from another address it does.
	 */
	#[Group('units')]
	#[DataProvider('codeForms')]
	public function testWrongCodesBlockTheAddress(string $method): void
	{
		$this->settings(true, false);

		for ($i = 1; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			static::assertSame('two_factor_wrong_code', $this->orchestrator->$method('000000'));
		static::assertSame('two_factor_blocked', $this->orchestrator->$method('000000'), 'The last wrong code blocks.');

		static::assertSame('two_factor_blocked', $this->orchestrator->$method(self::RIGHT_CODE));
		static::assertSame([], $this->orchestrator->buildTab()['new_codes']);

		$_SERVER['REMOTE_ADDR'] = '10.0.0.2';
		static::assertSame('', $this->orchestrator->$method(self::RIGHT_CODE));
	}

	/**
	 * The right code ends the block of the address: the wrong codes before count no more.
	 */
	#[Group('units')]
	#[DataProvider('codeForms')]
	public function testTheRightCodeResetsTheAddress(string $method): void
	{
		$this->settings(true, false);
		for ($i = 1; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->orchestrator->$method('000000');

		static::assertSame('', $this->orchestrator->$method(self::RIGHT_CODE));

		static::assertSame('two_factor_wrong_code', $this->orchestrator->$method('000000'));
	}

	#[Group('units')]
	/**
	 * The other sessions end: one of them must not keep the second factor when the admin requires it later.
	 * This one stays with the time of the end.
	 */
	#[Group('units')]
	public function testTurningItOffEndsTheOtherSessions(): void
	{
		$this->settings(true, false);

		static::assertSame('', $this->orchestrator->disable(self::RIGHT_CODE));

		static::assertFalse(new UserSession($this->session)->hasTwoFactor());
		static::assertSame(['UID' => 7, 'username' => 'anna', 'locale' => 'de_DE', 'last_password_change' => '2026-11-10 19:00:00'], $this->session->get('user'));
	}
}
