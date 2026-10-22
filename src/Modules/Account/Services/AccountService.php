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

namespace App\Modules\Account\Services;

use App\Shared\User\UserTokenService;
use App\Framework\Security\Crypt;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Modules\Users\UserStatus;
use Doctrine\DBAL\Exception;
use Throwable;

/**
 * Class AccountService
 *
 * Service for managing the user's own account including password updates, locale changes, and user token operations.
 */
class AccountService
{
	private readonly UserMainRepository $userMainRepository;
	private readonly UserTokenService $userTokenService;
	private readonly Crypt $crypt;
	private readonly Transactions $transactions;

	public function __construct(UserMainRepository $userMainRepository, UserTokenService $userTokenService, Crypt $crypt, Transactions $transactions)
	{
		$this->userMainRepository = $userMainRepository;
		$this->userTokenService   = $userTokenService;
		$this->crypt              = $crypt;
		$this->transactions       = $transactions;
	}

	/**
	 * Sets the password, uses the token and releases a not verified user in one transaction.
	 *
	 * @throws Exception
	 * @throws TranslatableModuleException
	 * @throws Throwable
	 */
	public function storeNewForcedPassword(int $UID, string $passwordToken, string $password): void
	{
		$this->transactions->begin();
		try
		{
			if ($this->updatePassword($UID, $password) === 0)
				throw new TranslatableModuleException('account', 'password_update_failed', ['UID' => $UID]);

			if ($this->userTokenService->useToken($passwordToken) === 0)
				throw new TranslatableModuleException('account', 'token_update_failed', ['UID' => $UID]);

			$user = $this->userMainRepository->findByIdSecured($UID);
			if (empty($user))
				throw new NotFoundException('users', 'user_not_found', ['UID' => $UID]);

			if ($user['status'] === UserStatus::NOT_VERIFIED->value &&
				$this->userMainRepository->update($UID, ['status' => UserStatus::REGISTERED->value]) === 0)
				throw new TranslatableModuleException('account', 'status_update_failed', ['UID' => $UID]);

			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * @throws Exception
	 */
	public function updatePassword(int $UID, string $password): int
	{
		$data = ['password' => $this->crypt->createPasswordHash($password)];

		return $this->userMainRepository->update($UID, $data);
	}

	/**
	 * @throws Exception
	 */
	public function updateLocale(int $UID, string $locale): int
	{
		return $this->userMainRepository->update($UID, ['locale' => $locale]);
	}


}