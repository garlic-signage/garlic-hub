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

namespace App\Shared\User\TwoFactor;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Security\Crypt;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\User\Repositories\Core\UserRecoveryCodesRepository;
use App\Shared\User\Repositories\Core\UserTwoFactorRepository;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * The second factor of the login: a TOTP (RFC 6238) with the defaults SHA1, 6 digits and 30 seconds, which every
 * authenticator app (Aegis, FreeOTP, Google Authenticator ...) knows, and recovery codes for a lost device.
 *
 * The secret is stored encrypted. It counts only after the user confirmed it with a valid code: a secret which
 * was never confirmed is a setup which is not finished. A code is accepted one time slot before and after the current
 * one (clocks differ), but never twice: the time slot of the last accepted code is stored.
 */
class TwoFactorService
{
	public const int RECOVERY_CODES = 10;
	private const int RECOVERY_CODE_LENGTH = 10;
	private const string RECOVERY_CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

	public function __construct(
		private readonly UserTwoFactorRepository $twoFactorRepository,
		private readonly UserRecoveryCodesRepository $recoveryCodesRepository,
		private readonly Crypt $crypt,
		private readonly ClockInterface $clock,
		private readonly Transactions $transactions,
		private readonly AuditLog $auditLog,
		private readonly string $issuer
	) {}

	/**
	 * @throws Exception
	 */
	public function isEnabled(int $UID): bool
	{
		$row = $this->twoFactorRepository->findFirstById($UID);

		return $row !== [] && $row['enabled_at'] !== null;
	}

	/**
	 * The secret for the app of the user, as QR code and as text. A setup which is not finished keeps its secret:
	 * a reload of the page shows the same code which the user may have scanned already.
	 *
	 * @return array{secret:string, qr_code:string}|null null when the second factor is on already
	 * @throws CryptoException
	 * @throws Exception
	 */
	public function prepareSetup(int $UID, string $username): ?array
	{
		$row = $this->twoFactorRepository->findFirstById($UID);
		if ($row !== [] && $row['enabled_at'] !== null)
			return null;

		$encrypted = (string) ($row['secret'] ?? '');
		if ($row === [])
		{
			$encrypted = Crypto::encrypt(TOTP::generate($this->clock)->getSecret(), $this->crypt->getEncryptionKey());
			$this->twoFactorRepository->insert(['UID' => $UID, 'secret' => $encrypted]);
		}
		$secret = $this->decrypt($encrypted);

		$totp = TOTP::createFromSecret($secret, $this->clock);
		// a colon separates the issuer from the label in the uri
		$totp->setLabel(str_replace(':', '', $username) ?: (string) $UID);
		$issuer = str_replace(':', '', $this->issuer);
		if ($issuer !== '')
			$totp->setIssuer($issuer);

		return ['secret' => $secret, 'qr_code' => $this->qrCode($totp->getProvisioningUri())];
	}

	/**
	 * The first valid code turns the second factor on.
	 *
	 * @return list<string>|null the new recovery codes, null when the code is wrong or there is no setup
	 * @throws CryptoException
	 * @throws Exception
	 * @throws Throwable
	 */
	public function confirmSetup(int $UID, string $code): ?array
	{
		$row = $this->twoFactorRepository->findFirstById($UID);
		if ($row === [] || $row['enabled_at'] !== null)
			return null;

		$timeSlot = $this->matchTimeSlot($this->decrypt((string) $row['secret']), $code);
		if ($timeSlot === null)
			return null;

		$this->transactions->begin();
		try
		{
			$this->twoFactorRepository->update($UID, ['enabled_at' => date('Y-m-d H:i:s'), 'last_time_slot' => $timeSlot]);
			$codes = $this->replaceRecoveryCodes($UID);
			$this->auditLog->record($UID, 'account', AuditAction::TWO_FACTOR_ENABLED, null, $UID);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}

		return $codes;
	}

	/**
	 * The second step of the login: a code of the app or a recovery code, which is used up then.
	 *
	 * @throws CryptoException
	 * @throws Exception
	 */
	public function verify(int $UID, string $code): bool
	{
		$row = $this->twoFactorRepository->findFirstById($UID);
		if ($row === [] || $row['enabled_at'] === null)
			return false;

		$code = $this->normalize($code);
		$timeSlot = $this->matchTimeSlot($this->decrypt((string) $row['secret']), $code);
		if ($timeSlot !== null)
			return $this->twoFactorRepository->takeTimeSlot($UID, $timeSlot);

		if (strlen($code) !== self::RECOVERY_CODE_LENGTH)
			return false;

		return $this->recoveryCodesRepository->useCode($UID, $this->hash($code));
	}

	/**
	 * New recovery codes, the old ones are invalid then. They are shown once: only the hashes are stored.
	 *
	 * @return list<string>
	 * @throws Exception
	 * @throws Throwable
	 */
	public function createRecoveryCodes(int $UID, string $code): array
	{
		// only with a valid code: with new codes a session which somebody took over would get past the second factor
		if (!$this->verify($UID, $code))
			return [];

		$this->transactions->begin();
		try
		{
			$codes = $this->replaceRecoveryCodes($UID);
			$this->auditLog->record($UID, 'account', AuditAction::RECOVERY_CODES_CREATED, null, $UID);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}

		return $codes;
	}

	/**
	 * @throws Exception
	 */
	public function countRecoveryCodes(int $UID): int
	{
		return $this->recoveryCodesRepository->countUnused($UID);
	}

	/**
	 * The user turns it off himself: only with a valid code, a session which somebody took over is not enough.
	 *
	 * @throws CryptoException
	 * @throws Exception
	 * @throws Throwable
	 */
	public function disable(int $UID, string $code): bool
	{
		if (!$this->verify($UID, $code))
			return false;

		$this->removeWithAudit($UID, $UID, 'account', AuditAction::TWO_FACTOR_DISABLED);

		return true;
	}

	/**
	 * The admin turns it off for a user who lost his device. The user sets it up again.
	 *
	 * @throws Exception
	 * @throws Throwable
	 */
	public function reset(int $UID, int $adminUID): void
	{
		$this->removeWithAudit($UID, $adminUID, 'users', AuditAction::TWO_FACTOR_RESET);
	}

	/**
	 * For a deleted user, inside the transaction of the deletion.
	 *
	 * @throws Exception
	 */
	public function remove(int $UID): void
	{
		$this->recoveryCodesRepository->deleteBy(['UID' => $UID]);
		$this->twoFactorRepository->delete($UID);
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 */
	private function removeWithAudit(int $UID, int $actorUID, string $module, AuditAction $action): void
	{
		$this->transactions->begin();
		try
		{
			$this->remove($UID);
			$this->auditLog->record($actorUID, $module, $action, null, $UID);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * The time slot of the code: now, one before or one after. The comparison takes the same time for every code.
	 *
	 * @param non-empty-string $secret
	 */
	private function matchTimeSlot(string $secret, string $code): ?int
	{
		$code = $this->normalize($code);
		if (preg_match('/^\d{6}$/', $code) !== 1)
			return null;

		$totp   = TOTP::createFromSecret($secret, $this->clock);
		$period = $totp->getPeriod();
		$now    = $this->clock->now()->getTimestamp();
		foreach ([0, -1, 1] as $offset)
		{
			$timestamp = $now + $offset * $period;
			if ($timestamp >= 0 && $totp->verify($code, $timestamp))
				return intdiv($timestamp, $period);
		}

		return null;
	}

	/**
	 * @return list<string>
	 * @throws Exception
	 */
	private function replaceRecoveryCodes(int $UID): array
	{
		$this->recoveryCodesRepository->deleteBy(['UID' => $UID]);

		$codes = [];
		while (count($codes) < self::RECOVERY_CODES)
		{
			$code = $this->randomCode();
			if (in_array($code, $codes, true))
				continue;

			$this->recoveryCodesRepository->insert(['UID' => $UID, 'code_hash' => $this->hash($code)]);
			$codes[] = $code;
		}

		// readable in two halves, the dash does not count (see normalize())
		return array_map(static fn(string $code): string => substr($code, 0, 5) . '-' . substr($code, 5), $codes);
	}

	private function randomCode(): string
	{
		$code = '';
		$max  = strlen(self::RECOVERY_CODE_ALPHABET) - 1;
		for ($i = 0; $i < self::RECOVERY_CODE_LENGTH; $i++)
			$code .= self::RECOVERY_CODE_ALPHABET[random_int(0, $max)];

		return $code;
	}

	/**
	 * The codes are random and long enough: a fast hash is enough, a slow one would cost time for every code.
	 */
	private function hash(string $code): string
	{
		return hash('sha256', $code);
	}

	/**
	 * What the user types: spaces of the app ("123 456"), the dash of a recovery code, capitals.
	 */
	private function normalize(string $code): string
	{
		return strtolower(str_replace([' ', '-'], '', trim($code)));
	}

	/**
	 * @return non-empty-string
	 * @throws CryptoException
	 */
	private function decrypt(string $encrypted): string
	{
		$secret = Crypto::decrypt($encrypted, $this->crypt->getEncryptionKey());
		if ($secret === '')
			throw new CryptoException('The secret of the second factor is empty.');

		return $secret;
	}

	private function qrCode(string $uri): string
	{
		$svg = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()))->writeString($uri);

		// inline in the page: without the xml declaration
		$start = strpos($svg, '<svg');

		return $start === false ? $svg : substr($svg, $start);
	}
}
