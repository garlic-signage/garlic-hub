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
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Users\Repositories\Edge\UserMainRepository;
use App\Modules\Users\UserStatus;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class ProfileService
 *
 * Service for managing user profiles including password updates, locale changes, and user token operations.
 */
class ProfileService extends AbstractBaseService
{
	private readonly UserMainRepository $userMainRepository;
	private readonly UserTokenService $userTokenService;
	private readonly Crypt $crypt;
	private readonly Transactions $transactions;

	public function __construct(UserMainRepository $userMainRepository, UserTokenService $userTokenService, Crypt $crypt, Transactions $transactions, LoggerInterface $logger)
	{
		$this->userMainRepository = $userMainRepository;
		$this->userTokenService   = $userTokenService;
		$this->crypt              = $crypt;
		$this->transactions       = $transactions;

		parent::__construct($logger);
	}

	/**
	 * @throws Exception
	 */
	public function storeNewForcedPassword(int $UID, string $passwordToken, string $password): int
	{
		try
		{
			$this->transactions->begin();
			$count = $this->updatePassword($UID, $password);
			if ($count === 0)
			{
				$this->addErrorMessage('profile//password_update_failed');
				throw new ModuleException('profile', 'Password update failed');
			}

			if ($this->userTokenService->useToken($passwordToken) === 0)
			{
				$this->addErrorMessage('profile//token_update_failed');
				throw new ModuleException('profile', 'Token update failed');
			}

			$user = $this->userMainRepository->findByIdSecured($UID);
			if (empty($user))
			{
				$this->addErrorMessage('users//user_not_found');
				throw new ModuleException('profile', 'User not found');
			}
			if ($user['status'] === UserStatus::NOT_VERIFICATED->value &&
				$this->userMainRepository->update($UID, ['status' => UserStatus::REGISTERED->value]) === 0)
				{
					$this->addErrorMessage('users//status_update_failed');
					throw new ModuleException('profile', 'User status update failed');
				}

			$this->transactions->commit();

			return $count;
		}
		catch (Throwable $e)
		{
			$this->logger->error($e->getMessage());
			$this->transactions->rollBack();
			return 0;
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