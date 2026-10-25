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
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Users\UserStatus;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UsersService;
use App\Shared\User\UserTokenService;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use Doctrine\DBAL\Exception;
use Throwable;

/**
 * Service class responsible for managing administrative operations
 * related to user management, including user creation, modification,
 * and deletion, as well as token and directory operations.
 */
class UsersAdminService
{

	private string $token = '';

	public function __construct(
		private readonly UserMainRepository $userMainRepository,
		private readonly UserAclRepository $userAclRepository,
		private readonly NodesService $nodesService,
		private readonly UserTokenService $userTokenService,
		private readonly AclValidator $aclValidator,
		private readonly Transactions $transactions,
		private readonly UserSession $userSession,
		private readonly UsersService $usersService
	) {}

	public function getToken(): string
	{
		return $this->token;
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
		// a deleted user is anonymized and stays deleted
		if (empty($user) || (int) $user['status'] === UserStatus::DELETED->value)
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
		return $this->userTokenService->findTokensByUID($UID);
	}

	/**
	 * @param array{username:string, email:string, status?: int} $postData
	 * @throws Exception
	 * @throws ModuleException
	 * @throws Throwable
	 */
	public function insertNewUser(array $postData): int
	{
		$this->transactions->begin();
		try
		{
			$this->assertUnique(0, $postData['username'], $postData['email']);

			/** @var array{username:string, email:string, status?: int} $saveData */
			$saveData = $this->collectCommonData($postData);

			$UID = (int) $this->userMainRepository->insert($saveData);
			if ($UID === 0)
				throw new ModuleException('users', 'Insert failed.');

			$this->token = $this->userTokenService->generateToken();
			$this->userTokenService->insertToken($UID, $this->token, TokenPurposes::INITIAL_PASSWORD);

			$this->nodesService->addUserDirectory($UID, $saveData['username']);

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
	 * "Deletes" a user by anonymizing: the row stays, so the UID is never given to somebody else and
	 * the content of the user (players, playlists) keeps an owner. The email is replaced, password,
	 * session and gender are cleared and the status is deleted, so nobody can log in. The username
	 * stays. Tokens and rights are removed, the media directory is deleted and its media are
	 * marked as deleted (the files stay in the storage).
	 *
	 * @throws AccessDeniedException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws Throwable
	 */
	public function deleteUser(int $UID): void
	{
		$this->transactions->begin();
		try
		{
			if (!$this->aclValidator->isModuleAdmin($this->userSession->getUID()))
				throw new AccessDeniedException('users', 'user_not_deletable', ['UID' => $UID]);

			if ($this->userMainRepository->findByIdSecured($UID) === [])
				throw new NotFoundException('users', 'user_not_found', ['UID' => $UID]);

			$this->userMainRepository->update($UID, [
				'email'      => 'deleted-' . $UID . '@garlic-hub.com',
				'password'   => '',
				'session_id' => null,
				'gender'     => null,
				'status'     => UserStatus::DELETED->value
			]);

			$this->userTokenService->deleteAllUserTokens($UID);
			// the rights must not outlive the user: SQLite does not cascade without a pragma
			$this->userAclRepository->delete($UID);

			$this->nodesService->deleteUserDirectory($UID);
			$this->usersService->invalidateCache($UID);

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
	 * @throws TranslatableModuleException
	 * @throws Throwable
	 */
	public function createPasswordResetToken(int $UID): string
	{
		// a link which can still be used is refreshed with the token buttons, not replaced by a second one
		if ($this->hasUsableLinkToken($UID))
			throw new TranslatableModuleException('users', 'tokens_exists', ['UID' => $UID]);

		$this->transactions->begin();
		try
		{
			if ($this->userMainRepository->update($UID, ['password' => '']) === 0)
				throw new TranslatableModuleException('users', 'password_reset_failed', ['UID' => $UID]);

			// without password there is no reason to keep remember-me logins alive
			$this->userTokenService->deleteByUserPurpose($UID, TokenPurposes::AUTOLOGIN->value);

			$token = $this->userTokenService->generateToken();
			if ($this->userTokenService->insertToken($UID, $token, TokenPurposes::PASSWORD_RESET) === '')
				throw new TranslatableModuleException('users', 'password_reset_failed', ['UID' => $UID]);

			$this->transactions->commit();

			return $token;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * Remember-me logins and expired links do not count: they are no reason to refuse a reset.
	 *
	 * @throws Exception
	 */
	private function hasUsableLinkToken(int $UID): bool
	{
		$now = date('Y-m-d H:i:s');
		/** @var list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}> $tokens */
		$tokens = $this->userTokenService->findTokensByUID($UID);
		foreach ($tokens as $token)
		{
			if ($token['purpose'] !== TokenPurposes::AUTOLOGIN->value && $token['expires_at'] > $now)
				return true;
		}

		return false;
	}

	/**
	 * @param array{username:string, email:string, locale?: string, status?: int} $postData
	 * @throws Exception
	 * @throws TranslatableModuleException
	 */
	public function updateUser(int $UID, array $postData): int
	{
		$this->assertUnique($UID, $postData['username'], $postData['email']);

		$saveData = $this->collectCommonData($postData);
		$affected = $this->userMainRepository->update($UID, $saveData);

		// the user is cached for a day: a new status (blocked) or locale must count at once
		$this->usersService->invalidateCache($UID);

		return $affected;
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
	 * @throws TranslatableModuleException
	 */
	private function assertUnique(int $UID, string $username, string $email): void
	{
		// if there is similar user we need to check ig the UID is identic,
		// it could be possible that the user is try to edit the username and or email
		// of an existing user.
		// In this case it should be possible

		/** @var array{UID: int, username:string, email:string} $existing */
		foreach ($this->userMainRepository->findExistingUser($username, $email) as $existing)
		{
			if ($existing['username'] === $username && (int) $existing['UID'] !== $UID)
				throw new TranslatableModuleException('users', 'username_exists', ['username' => $username]);

			if ($existing['email'] === $email && (int) $existing['UID'] !== $UID)
				throw new TranslatableModuleException('users', 'email_exists', ['email' => $email]);
		}
	}

}