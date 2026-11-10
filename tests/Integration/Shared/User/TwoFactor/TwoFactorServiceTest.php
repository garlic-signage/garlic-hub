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

namespace Tests\Integration\Shared\User\TwoFactor;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Security\Crypt;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\User\Repositories\Core\UserRecoveryCodesRepository;
use App\Shared\User\Repositories\Core\UserTwoFactorRepository;
use App\Shared\User\TwoFactor\TwoFactorService;
use Defuse\Crypto\Crypto;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Support\FixedClock;

/**
 * The second factor with the real database and a clock which the test moves. Users: 1 admin, 2 anna, 3 bernd.
 */
#[Group('auth')]
class TwoFactorServiceTest extends ContainerTestCase
{
	// RFC 6238, appendix B: the secret "12345678901234567890" as base32
	private const string RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

	private FixedClock $clock;
	private TwoFactorService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->clock   = new FixedClock(1_800_000_000);
		$this->service = new TwoFactorService(
			new UserTwoFactorRepository($this->connection),
			new UserRecoveryCodesRepository($this->connection),
			$this->container->get(Crypt::class),
			$this->clock,
			$this->container->get(Transactions::class),
			$this->container->get(AuditLog::class),
			'Garlic-Hub'
		);
	}

	private function codeOf(string $secret, int $offset = 0): string
	{
		static::assertNotSame('', $secret);
		$timestamp = max(0, $this->clock->now()->getTimestamp() + $offset);

		return TOTP::createFromSecret($secret, $this->clock)->at($timestamp);
	}

	/**
	 * @return list<string> the recovery codes
	 */
	private function enable(int $UID = 2): array
	{
		$secret = $this->service->prepareSetup($UID, 'anna')['secret'] ?? '';
		$codes  = $this->service->confirmSetup($UID, $this->codeOf($secret));
		static::assertNotNull($codes);
		// the next code is in a new time slot
		$this->clock->moveBy(30);

		return $codes;
	}

	private function secretOf(int $UID): string
	{
		return Crypto::decrypt((string) $this->connection->fetchOne('SELECT secret FROM user_two_factor WHERE UID = ?', [$UID]), $this->container->get(Crypt::class)->getEncryptionKey());
	}

	public function testTheSetupShowsAQrCodeAndKeepsItsSecretForAReload(): void
	{
		$first  = $this->service->prepareSetup(2, 'anna');
		$second = $this->service->prepareSetup(2, 'anna');

		static::assertNotNull($first);
		static::assertSame($first, $second);
		static::assertStringStartsWith('<svg', $first['qr_code']);
		static::assertFalse($this->service->isEnabled(2));
	}

	public function testTheSecretIsStoredEncrypted(): void
	{
		$secret = $this->service->prepareSetup(2, 'anna')['secret'] ?? '';

		static::assertStringNotContainsString($secret, (string) $this->connection->fetchOne('SELECT secret FROM user_two_factor WHERE UID = 2'));
		static::assertSame($secret, $this->secretOf(2));
	}

	public function testAWrongCodeDoesNotTurnItOn(): void
	{
		$this->service->prepareSetup(2, 'anna');

		static::assertNull($this->service->confirmSetup(2, '000000'));
		static::assertFalse($this->service->isEnabled(2));
	}

	public function testTheFirstValidCodeTurnsItOnAndGivesTheRecoveryCodes(): void
	{
		$codes = $this->enable();

		static::assertTrue($this->service->isEnabled(2));
		static::assertCount(TwoFactorService::RECOVERY_CODES, array_unique($codes));
		static::assertSame(TwoFactorService::RECOVERY_CODES, $this->service->countRecoveryCodes(2));
		foreach ($codes as $code)
			static::assertMatchesRegularExpression('/^[a-z2-9]{5}-[a-z2-9]{5}$/', $code);
		// only the hashes are stored
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_recovery_codes WHERE code_hash = ?', [str_replace('-', '', $codes[0])]));
		static::assertNull($this->service->prepareSetup(2, 'anna'), 'There is nothing to set up while it is on.');
	}

	public function testTheCodesOfTheRfcAreAccepted(): void
	{
		$this->connection->insert('user_two_factor', ['UID' => 2, 'secret' => Crypto::encrypt(self::RFC_SECRET, $this->container->get(Crypt::class)->getEncryptionKey()), 'enabled_at' => '2026-01-01 00:00:00']);

		// the 8 digits of the RFC, the last 6 of them are the code of 6 digits
		foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $time => $code)
		{
			$this->clock->moveBy($time - $this->clock->now()->getTimestamp());
			static::assertTrue($this->service->verify(2, $code), 'The code at ' . $time);
		}
	}

	public function testACodeIsAcceptedOnlyOnce(): void
	{
		$this->enable();
		$code = $this->codeOf($this->secretOf(2));

		static::assertTrue($this->service->verify(2, $code));
		static::assertFalse($this->service->verify(2, $code));
	}

	public function testTheCodeOfOneSlotBeforeAndAfterIsAcceptedButNotOfTwo(): void
	{
		$this->enable();
		// away from the slot of the confirmation, which is taken
		$this->clock->moveBy(60);
		$secret = $this->secretOf(2);

		static::assertFalse($this->service->verify(2, $this->codeOf($secret, -60)));
		static::assertTrue($this->service->verify(2, $this->codeOf($secret, -30)));
		static::assertFalse($this->service->verify(2, $this->codeOf($secret, 60)));
		static::assertTrue($this->service->verify(2, $this->codeOf($secret, 30)));
		// the slot after was taken: the current one is older now
		static::assertFalse($this->service->verify(2, $this->codeOf($secret)));
	}

	public function testTheCodeOfTheAppMayHaveASpace(): void
	{
		$this->enable();
		$code = $this->codeOf($this->secretOf(2));

		static::assertTrue($this->service->verify(2, substr($code, 0, 3) . ' ' . substr($code, 3)));
	}

	public function testARecoveryCodeWorksOnceWithoutDashAndInCapitals(): void
	{
		$codes = $this->enable();

		static::assertTrue($this->service->verify(2, strtoupper(str_replace('-', '', $codes[0]))));
		static::assertFalse($this->service->verify(2, $codes[0]));
		static::assertTrue($this->service->verify(2, $codes[1]));
		static::assertSame(TwoFactorService::RECOVERY_CODES - 2, $this->service->countRecoveryCodes(2));
	}

	public function testNewRecoveryCodesMakeTheOldOnesInvalid(): void
	{
		$old = $this->enable();

		$new = $this->service->createRecoveryCodes(2, $old[0]);

		static::assertCount(TwoFactorService::RECOVERY_CODES, $new);
		static::assertFalse($this->service->verify(2, $old[1]));
		static::assertTrue($this->service->verify(2, $new[0]));
	}

	/**
	 * With new codes a session which somebody took over would get past the second factor for good.
	 */
	public function testNewRecoveryCodesOnlyWithAValidCode(): void
	{
		$old = $this->enable();

		static::assertSame([], $this->service->createRecoveryCodes(2, ''));
		static::assertSame([], $this->service->createRecoveryCodes(2, '000000'));
		static::assertTrue($this->service->verify(2, $old[0]), 'the old codes still work');

		static::assertCount(TwoFactorService::RECOVERY_CODES, $this->service->createRecoveryCodes(2, $this->codeOf($this->secretOf(2))));
	}

	public function testWithoutTheSecondFactorNothingIsVerified(): void
	{
		$this->service->prepareSetup(2, 'anna');

		static::assertFalse($this->service->verify(2, $this->codeOf($this->secretOf(2))));
		static::assertSame([], $this->service->createRecoveryCodes(2, $this->codeOf($this->secretOf(2))));
	}

	public function testTheUserTurnsItOffOnlyWithACode(): void
	{
		$codes = $this->enable();

		static::assertFalse($this->service->disable(2, '000000'));
		static::assertTrue($this->service->isEnabled(2));

		static::assertTrue($this->service->disable(2, $codes[0]));
		static::assertFalse($this->service->isEnabled(2));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_recovery_codes WHERE UID = 2'));
	}

	public function testTheAdminResetsItAndTheOtherUsersKeepTheirs(): void
	{
		$this->enable(2);
		$this->enable(3);

		$this->service->reset(2, 1);

		static::assertFalse($this->service->isEnabled(2));
		static::assertTrue($this->service->isEnabled(3));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_two_factor WHERE UID = 2'));
		static::assertSame(0, $this->service->countRecoveryCodes(2));
		static::assertSame(TwoFactorService::RECOVERY_CODES, $this->service->countRecoveryCodes(3));
		// a new setup gets a new secret
		static::assertNotNull($this->service->prepareSetup(2, 'anna'));
	}

	public function testNewRecoveryCodesAndTurningItOffKeepTheCodesOfOtherUsers(): void
	{
		$codes = $this->enable(2);
		$this->enable(3);

		static::assertCount(TwoFactorService::RECOVERY_CODES, $this->service->createRecoveryCodes(2, $codes[0]));
		static::assertSame(TwoFactorService::RECOVERY_CODES, $this->service->countRecoveryCodes(3));

		$this->service->disable(2, $this->codeOf($this->secretOf(2)));
		static::assertSame(TwoFactorService::RECOVERY_CODES, $this->service->countRecoveryCodes(3));
	}

	/**
	 * A second confirmation would give new recovery codes past the check of createRecoveryCodes().
	 */
	public function testASecondConfirmationIsRefused(): void
	{
		$this->enable();

		static::assertNull($this->service->confirmSetup(2, $this->codeOf($this->secretOf(2))));
	}

	public function testARecoveryCodeMayHaveTabsAndLineBreaksAround(): void
	{
		$codes = $this->enable();

		static::assertTrue($this->service->verify(2, "\t" . $codes[0] . "\n"));
	}

	/**
	 * Every change is committed and audited: by the user himself, the reset by the admin.
	 */
	public function testEveryChangeIsCommittedAndAudited(): void
	{
		$recorded = [];
		$auditLog = $this->createStub(AuditLog::class);
		$auditLog->method('record')->willReturnCallback(static function (int $UID, string $module, AuditAction $action, int|string|null $unitId = null, ?int $targetUID = null) use (&$recorded): void
		{
			$recorded[] = [$UID, $module, $action, $targetUID];
		});
		$transactions = $this->createMock(Transactions::class);
		$transactions->expects($this->exactly(5))->method('begin');
		$transactions->expects($this->exactly(5))->method('commit');
		$transactions->expects($this->never())->method('rollBack');
		$this->service = new TwoFactorService(new UserTwoFactorRepository($this->connection), new UserRecoveryCodesRepository($this->connection), $this->container->get(Crypt::class), $this->clock, $transactions, $auditLog, 'Garlic-Hub');

		$codes = $this->enable(2);
		$codes = $this->service->createRecoveryCodes(2, $codes[0]);
		static::assertTrue($this->service->disable(2, $codes[0]));
		$this->enable(3);
		$this->service->reset(3, 1);

		static::assertSame([
			[2, 'account', AuditAction::TWO_FACTOR_ENABLED, 2],
			[2, 'account', AuditAction::RECOVERY_CODES_CREATED, 2],
			[2, 'account', AuditAction::TWO_FACTOR_DISABLED, 2],
			[3, 'account', AuditAction::TWO_FACTOR_ENABLED, 3],
			[1, 'users', AuditAction::TWO_FACTOR_RESET, 3],
		], $recorded);
	}

	/**
	 * The app shows issuer and user. A colon would separate them in the address, so it is left out.
	 */
	public function testTheAddressOfTheQrCodeNamesTheIssuerAndTheUser(): void
	{
		$uri = $this->service->provisioningUri(self::RFC_SECRET, 2, 'an:na');
		static::assertStringStartsWith('otpauth://totp/Garlic-Hub%3Aanna?', $uri);
		static::assertStringContainsString('issuer=Garlic-Hub', $uri);

		static::assertStringStartsWith('otpauth://totp/Garlic-Hub%3A2?', $this->service->provisioningUri(self::RFC_SECRET, 2, ''), 'without a name the id');

		$withoutIssuer = new TwoFactorService(new UserTwoFactorRepository($this->connection), new UserRecoveryCodesRepository($this->connection), $this->container->get(Crypt::class), $this->clock, $this->container->get(Transactions::class), $this->container->get(AuditLog::class), ':');
		$uri = $withoutIssuer->provisioningUri(self::RFC_SECRET, 2, 'anna');
		static::assertStringStartsWith('otpauth://totp/anna?', $uri);
		static::assertStringNotContainsString('issuer', $uri);

		$colon = new TwoFactorService(new UserTwoFactorRepository($this->connection), new UserRecoveryCodesRepository($this->connection), $this->container->get(Crypt::class), $this->clock, $this->container->get(Transactions::class), $this->container->get(AuditLog::class), 'Garlic:Hub');
		static::assertStringContainsString('issuer=GarlicHub', $colon->provisioningUri(self::RFC_SECRET, 2, 'anna'));
	}
}
