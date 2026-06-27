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


namespace App\Modules\Auth;

use App\Framework\Core\Cookie;
use App\Framework\Exceptions\AuthException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\UserTokenService;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class AutoLoginService
{
	public const string COOKIE_NAME_AUTO_LOGIN = 'UserLogin';
	public function __construct(private readonly UserTokenService   $userTokenService,
								private readonly Cookie             $cookie,
								private readonly LoggerInterface    $logger)
	{
	}

	/**
	 * @return string
	 */
	public function loadTokenFromCookie(): string
	{
		// no cookie? that's it
		if (!$this->cookie->hasCookie(self::COOKIE_NAME_AUTO_LOGIN))
			throw new AuthException('No cookie for autologin was found.');

		$cookiePayload = $this->cookie->getCookie(self::COOKIE_NAME_AUTO_LOGIN);
		if ($cookiePayload === null)
			throw new AuthException('No token in cookie cookie found.');

		return $cookiePayload;
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws DateMalformedStringException
	 */
	public function loginSilent(string $cookieToken): int
	{
		$this->logger->info('Attempt silent login.');
		$dbToken = $this->userTokenService->findByToken($cookieToken);
		if ($dbToken === null)
			throw new AuthException('Autologin token not found.');

		if ($dbToken['expires_at'] < new DateTime()->format('Y-m-d H:i:s'))
		{
			$this->userTokenService->deleteExpiredToken();
			throw new AuthException('Autologin expired.');
		}
		if ($dbToken['used_at']  !== null)
		{
			$this->userTokenService->deleteAllUserTokens($dbToken['UID']);
			throw new AuthException('Cookie manipulation. Contact administrator.');
		}

		$this->userTokenService->useToken($cookieToken);
		// rotate Autologin
		$this->createAutologinCookie($dbToken['UID']);

		return (int) $dbToken['UID'];
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Exception
	 */
	public function createAutologinCookie(int $UID): void
	{
		// check if there is another
		$token = $this->userTokenService->generateToken();
		$this->userTokenService->insertToken($UID, $token, TokenPurposes::AUTOLOGIN);
		$this->cookie->createCookie(
			self::COOKIE_NAME_AUTO_LOGIN,
			$token,
			new DateTime(UserTokenService::AUTOLOGIN_EXPIRE)
		);
	}

	/**
	 * @throws Exception
	 */
	public function removeAutoLogin(): void
	{
		$token = $this->cookie->getCookie(self::COOKIE_NAME_AUTO_LOGIN);
		if ($token !== null)
			$this->userTokenService->deleteByToken($token);

		$this->cookie->deleteCookie(self::COOKIE_NAME_AUTO_LOGIN);
	}

}