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

namespace App\Modules\Users\Services;

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Services\NodesService;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Modules\Users\UserStatus;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

class UsersAdminCreateService
{
	public function __construct(private readonly UserMainRepository $userMainRepository,
								private readonly UserAclRepository $userAclRepository,
								private readonly NodesService $nodesService,
								private readonly Transactions $transactions,
								private readonly LoggerInterface $logger)
	{
	}

	/**
	 * @throws Exception
	 */
	public function hasAdminUser(): bool
	{
		return ($this->userMainRepository->findByIdSecured(1) !== []);
	}

	public function creatLockfile(): bool
	{
		if (is_int(file_put_contents(INSTALL_LOCK_FILE, date('Y-m-d H:i:s'))))
			return true;

		return false;
	}

	public function logAlarm(): void
	{
		$this->logger->error('Logfile was removed. Create a new one.');
	}

	/**
	 * @param array{username:string, email:string, locale: string, password:string} $postData
	 * @throws Exception
	 * @throws ModuleException
	 * @throws Throwable
	 */
	public function insertNewAdminUser(array $postData, Config $config): int
	{
		$this->transactions->begin();
		try
		{
			if (file_exists(INSTALL_LOCK_FILE) || $this->hasAdminUser())
				throw new TranslatableModuleException('users', 'admin_exists');

			$saveData = $this->collectCommonData($postData);
			$UID = (int) $this->userMainRepository->insert($saveData);
			if ($UID !== 1)
				throw new ModuleException('users', 'Insert admin user failed.');

			$this->nodesService->addUserDirectory($UID, $saveData['username']);

			$this->userAclRepository->addAdminRights($config);
			if (!$this->creatLockfile())
				throw new ModuleException('users', 'Lockfile could not created.');

			$this->transactions->commit();
			return $UID;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * @param array{username:string, email:string, locale: string, password:string} $postData
	 * @return array{UID: int, username:string, email:string, locale: string, status: int, password:string}
	 */
	private function collectCommonData(array $postData): array
	{
		return [
			'UID' => 1,
			'username' => $postData['username'],
			'email' => $postData['email'],
			'locale' => $postData['locale'],
			'status' => UserStatus::ADMIN->value,
			'password' => password_hash($postData['password'], PASSWORD_DEFAULT)
		];
	}


}