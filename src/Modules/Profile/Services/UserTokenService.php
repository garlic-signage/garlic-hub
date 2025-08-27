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

namespace App\Modules\Profile\Services;

use App\Framework\Core\Crypt;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Users\Repositories\Edge\UserTokensRepository;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

/**
 * This service handles operations related to user tokens, such as generating, finding, refreshing, and deleting tokens.
 */
class UserTokenService extends AbstractBaseService
{
	private const string TOKEN_EXP_HOURS_PWD_INIT = '24';
	private const string TOKEN_EXPIRATION_HOURS = '2';
	private readonly UserTokensRepository $userTokensRepository;
	private readonly Crypt $crypt;

	public function __construct(UserTokensRepository $userTokensRepository, Crypt $crypt, LoggerInterface $logger)
	{
		$this->userTokensRepository = $userTokensRepository;
		$this->crypt                = $crypt;
		parent::__construct($logger);
	}

	/**
	 * @return array{"UID":int, "company_id":int, "username":string, "status":int, "purpose":string}|null
	 * @throws Exception
	 */
	public function findByTokenForAction(string $token): ?array
	{
		$token = @hex2bin($token);
		if ($token === false)
			return null;

		$token =  $this->userTokensRepository->findFirstByToken($token);
		if ($token === [])
			return null;

		/** @var array{"UID":int, "company_id":int, "username":string, "status":int, "purpose":string} $token */
		return $token;
	}


	/**
	 * @return array{"UID":int, "company_id":int, "username":string, "status":int, "purpose":string}|null
	 * @throws DateMalformedStringException|Exception
	 */
	public function findByToken(string $token): ?array
	{
		$token = @hex2bin($token);
		if ($token === false)
			return null;

		$result = $this->userTokensRepository->findFirstByToken($token);
		$now = new DateTime();
		if ($result === [] || isset($result['used_at']) || new DateTime($result['expires_at']) < $now)
			return null;

		return [
			'UID'        => (int) $result['UID'],
			'company_id' => (int) $result['company_id'],
			'username'   => $result['username'],
			'status'     => (int)$result['status'],
			'purpose'    => $result['purpose']
		];
	}

	/**
	 * @return list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 * @throws Exception
	 */
	public function findTokenByUID(int $UID): array
	{
		return $this->userTokensRepository->findValidByUID($UID);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	public function insertToken(int $UID, TokenPurposes $purpose): string
	{
		if ($purpose === TokenPurposes::INITIAL_PASSWORD)
			$expiresAt = date('Y-m-d H:i:s', strtotime('+'.self::TOKEN_EXP_HOURS_PWD_INIT.' hour'));
		else
			$expiresAt = date('Y-m-d H:i:s', strtotime('+'.self::TOKEN_EXPIRATION_HOURS.' hour'));


		$token = [
			'UID' => $UID,
			'purpose' => $purpose->value,
			'token' => $this->crypt->generateRandomBytes(),
			'expires_at' => $expiresAt
		];
		return (string) $this->userTokensRepository->insert($token);
	}

	/**
	 * @throws Exception
	 */
	public function deleteToken(string $token): int
	{
		$token = @hex2bin($token);
		if ($token === false)
			return 0;

		return $this->userTokensRepository->delete($token);
	}

	/**
	 * @throws Exception
	 */
	public function refreshToken(string $token, string $purpose): int
	{
		$token = @hex2bin($token);
		if ($token === false || $purpose === '')
			return 0;

		if ($purpose === TokenPurposes::INITIAL_PASSWORD->value)
			$expiresAt = date('Y-m-d H:i:s', strtotime('+'.self::TOKEN_EXP_HOURS_PWD_INIT.' hour'));
		else
			$expiresAt = date('Y-m-d H:i:s', strtotime('+'.self::TOKEN_EXPIRATION_HOURS.' hour'));

		return $this->userTokensRepository->refresh($token, $expiresAt);
	}

	/**
	 * @throws Exception
	 */
	public function useToken(string $token): int
	{
		$token = @hex2bin($token);
		if ($token === false)
			return 0;

		return $this->userTokensRepository->update($token, ['used_at' => date('Y-m-d H:i:s')]);
	}

}