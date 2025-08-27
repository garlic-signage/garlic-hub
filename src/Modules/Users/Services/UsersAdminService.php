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

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Repositories\Edge\UserMainRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service class responsible for managing administrative operations
 * related to user management, including user creation, modification,
 * and deletion, as well as token and directory operations.
 */
class UsersAdminService extends AbstractBaseService
{
	private readonly UserMainRepository $userMainRepository;
	private readonly Transactions $transactions;
	private readonly NodesService $nodesService;
	private readonly UserTokenService $userTokenService;
	private readonly AclValidator $aclValidator;


	public function __construct(UserMainRepository $userMainRepository, NodesService $nodesService, UserTokenService $userTokenService,AclValidator $aclValidator, Transactions $transactions, LoggerInterface $logger)
	{
		$this->userMainRepository = $userMainRepository;
		$this->userTokenService   = $userTokenService;
		$this->nodesService       = $nodesService;
		$this->aclValidator       = $aclValidator;
		$this->transactions       = $transactions;

		parent::__construct($logger);
	}

	/**
	 * @return array{UID: int,
	 * company_id: int,
	 * status: int,
	 * locale: string,
	 * email:string,
	 * username:string,
	 * tokens:list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 *}|array{}
	 *
	 * @throws Exception
	 */
	public function loadForAdminEdit(int $UID): array
	{
		$user = $this->userMainRepository->findByIdSecured($UID);
		if (empty($user))
			return [];

		$user['tokens'] = $this->loadUserTokensForAdminEdit($UID);
		return $user;
	}

	/**
	 * @return list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 * @throws Exception
	 */
	public function loadUserTokensForAdminEdit(int $UID): array
	{
		return $this->userTokenService->findTokenByUID($UID);
	}

	/**
	 * @param array{username:string, email:string, status?: int} $postData
	 * @throws Exception
	 */
	public function insertNewUser(array $postData): int
	{
		try
		{
			$this->transactions->begin();
			if (!$this->isUnique(0, $postData['username'], $postData['email']))
				throw new ModuleException('users', 'User or email already exists.');

			/** @var array{username:string, email:string, status?: int} $saveData */
			$saveData = $this->collectCommonData($postData);

			$UID = (int) $this->userMainRepository->insert($saveData);
			if ($UID === 0)
				throw new ModuleException('users', 'Insert failed.');

			$this->userTokenService->insertToken($UID, TokenPurposes::INITIAL_PASSWORD);

			$this->nodesService->UID = $this->UID;
			$nodeId = $this->nodesService->addUserDirectory($UID, $saveData['username']);
			if ($nodeId === 0)
			{
				$this->addErrorMessage('create_media_dir_failed');
				throw new ModuleException('users', 'Create mediapool user directory failed.');
			}

			$this->transactions->commit();

			return $UID;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error($e->getMessage());
			return 0;
		}
	}

	/**
	 * @throws Exception
	 */
	public function deleteUser(int $UID): bool
	{
		try
		{
			$this->transactions->begin();

			if (!$this->aclValidator->isModuleAdmin($this->UID))
				throw new ModuleException('users', 'No rights to delete user');

			if ($this->userMainRepository->delete($UID) === 0)
				throw new ModuleException('users', 'Remove the user from db-table failed.');

			$this->nodesService->UID = $this->UID;
			$this->nodesService->deleteUserDirectory($UID);

			$this->transactions->commit();
			return true;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error($e->getMessage());
			$this->addErrorMessage('delete_user_failed');
			return false;
		}
	}

	/**
	 * @throws Exception
	 */
	public function createPasswordResetToken(int $UID): string
	{
		// check first if there is another valid token
		$tokens = $this->userTokenService->findTokenByUID($UID);
		if ($tokens !== [])
		{
			$this->addErrorMessage('tokens_exists');
			return '';
		}
		try
		{
			$this->transactions->begin();
			if($this->userMainRepository->update($UID, ['password' => '']) === 0)
				throw new ModuleException('users', 'Password reset failed.');

			$id = $this->userTokenService->insertToken($UID, TokenPurposes::PASSWORD_RESET);
			if($id === '')
				throw new ModuleException('users', 'Password reset failed.');

			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error($e->getMessage());
			$this->addErrorMessage('password_reset_failed');
			return '';
		}
		return $id;
	}

	/**
	 * @param array{username:string, email:string, locale?: string, status?: int} $postData
	 * @throws Exception
	 */
	public function updateUser(int $UID, array $postData): int
	{
		if (!$this->isUnique($UID, $postData['username'], $postData['email']))
			return 0;

		$saveData = $this->collectCommonData($postData);

		return $this->userMainRepository->update($UID, $saveData);
	}

	/**
	 * @param array{username?:string, email?:string, locale?: string, status?: int} $postData
	 * @return array{username?:string, email?:string, locale?: string, status?: int}
	 */
	private function collectCommonData(array $postData): array
	{
		$saveData = [];
		if (isset($postData['username']))
			$saveData['username'] = $postData['username'];

		if (isset($postData['email']))
			$saveData['email'] = $postData['email'];

		if (isset($postData['locale']))
			$saveData['locale'] = $postData['locale'];

		if (isset($postData['status']))
			$saveData['status'] = $postData['status'];

		return $saveData;
	}

	/**
	 * @throws Exception
	 */
	private function isUnique(int $UID, string $username, string $email): bool
	{
		$result =  $this->userMainRepository->findExistingUser($username, $email);

		if ($result === [])
			return true;

		// if there is similar user we need to check ig the UID is identically
		// it could be possible that user is try to edit the username and or email
		// of an existing user.
		// In this case it should be possible

		/** @var array{UID: int, username:string, email:string} $existing */
		foreach ($result as $existing)
		{
			if ($existing['username'] === $username && (int) $existing['UID'] !== $UID)
			{
				$this->addErrorMessage('username_exists');
				return false;
			}

			if ($existing['email'] === $email && (int) $existing['UID'] !== $UID)
			{
				$this->addErrorMessage('email_exists');
				return false;
			}
		}

		return true;
	}

}