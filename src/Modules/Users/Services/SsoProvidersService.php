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

namespace App\Modules\Users\Services;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Security\Crypt;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\User\Repositories\Core\SsoProvidersRepository;
use App\Shared\User\Repositories\Core\UserSsoRepository;
use App\Shared\User\Sso\SsoProvider;
use App\Shared\User\UsersService;
use Defuse\Crypto\Crypto;
use Doctrine\DBAL\Exception;
use Throwable;

/**
 * The OpenID Connect providers of the login, which the module admin of users sets up for his company.
 * The client secret is stored encrypted and never shown again: an empty field keeps it.
 */
class SsoProvidersService
{
	private const string MODULE_NAME = 'users';
	private const int MAX_NAME_LENGTH = 100;
	private const int MAX_LENGTH = 255;

	public function __construct(
		private readonly SsoProvidersRepository $providersRepository,
		private readonly UserSsoRepository $userSsoRepository,
		private readonly AclValidator $aclValidator,
		private readonly UsersService $usersService,
		private readonly Crypt $crypt,
		private readonly Transactions $transactions,
		private readonly AuditLog $auditLog
	) {}

	/**
	 * @throws Exception
	 */
	public function mayManage(int $UID): bool
	{
		return $this->aclValidator->isModuleAdmin($UID);
	}

	/**
	 * @return list<SsoProvider>
	 * @throws Exception
	 */
	public function findAll(int $UID): array
	{
		return $this->providersRepository->findAllOfCompany($this->companyOf($UID));
	}

	/**
	 * Only a provider of the company of the admin.
	 *
	 * @throws Exception
	 */
	public function find(int $UID, int $providerId): ?SsoProvider
	{
		$provider = $this->providersRepository->findProvider($providerId);

		return $provider !== null && $provider->companyId === $this->companyOf($UID) ? $provider : null;
	}

	/**
	 * A new provider (null) or a change. The translation keys of the errors, none when it is stored.
	 *
	 * @param array{name:string, issuer:string, client_id:string, client_secret:string, allowed_domains:string, enabled:bool} $input
	 * @return list<string>
	 * @throws Throwable
	 */
	public function save(int $UID, ?SsoProvider $provider, array $input): array
	{
		$name    = trim($input['name']);
		$issuer  = rtrim(trim($input['issuer']), '/');
		$secret  = trim($input['client_secret']);
		$domains = SsoProvider::parseDomains($input['allowed_domains']);
		$errors  = $this->validate($UID, $provider, $name, $issuer, trim($input['client_id']), $secret, $domains);
		if ($errors !== [])
			return $errors;

		$fields = [
			'name'            => $name,
			'issuer'          => $issuer,
			'client_id'       => trim($input['client_id']),
			'allowed_domains' => implode(', ', $domains),
			'enabled'         => $input['enabled'] ? 1 : 0
		];
		if ($secret !== '')
			$fields['client_secret'] = Crypto::encrypt($secret, $this->crypt->getEncryptionKey());

		if ($provider === null)
		{
			$inserted   = $this->providersRepository->insert(['company_id' => $this->companyOf($UID)] + $fields);
			// @infection-ignore-all the id goes into the audit log as text anyway
			$providerId = (int) $inserted;
		}
		else
		{
			$providerId = $provider->id;
			$this->providersRepository->update($providerId, $fields);
		}

		// the secret does not go into the log
		unset($fields['client_secret']);
		$this->auditLog->record($UID, self::MODULE_NAME, AuditAction::SSO_PROVIDER_SAVED, $providerId, null, $fields + ['secret_changed' => $secret !== '']);

		return [];
	}

	/**
	 * The users stay: they log in by password, or by the provider again when it is set up once more.
	 *
	 * @throws Throwable
	 */
	public function delete(int $UID, SsoProvider $provider): void
	{
		$this->transactions->begin();
		try
		{
			$this->userSsoRepository->removeOfProvider($provider->id);
			$this->providersRepository->delete($provider->id);
			$this->auditLog->record($UID, self::MODULE_NAME, AuditAction::SSO_PROVIDER_DELETED, $provider->id, null, ['name' => $provider->name]);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * @param list<string> $domains
	 * @return list<string>
	 * @throws Exception
	 */
	private function validate(int $UID, ?SsoProvider $provider, string $name, string $issuer, string $clientId, string $secret, array $domains): array
	{
		$errors = [];
		if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH)
			$errors[] = 'sso_name_invalid';

		// OpenID Connect requires https
		if (filter_var($issuer, FILTER_VALIDATE_URL) === false || parse_url($issuer, PHP_URL_SCHEME) !== 'https' || strlen($issuer) > self::MAX_LENGTH)
			$errors[] = 'sso_issuer_invalid';

		if ($clientId === '' || strlen($clientId) > self::MAX_LENGTH)
			$errors[] = 'sso_client_id_invalid';

		// a new provider needs the secret, a stored one keeps it
		if ($provider === null && $secret === '')
			$errors[] = 'sso_client_secret_missing';

		foreach ($domains as $domain)
		{
			if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false || !str_contains($domain, '.'))
			{
				$errors[] = 'sso_domains_invalid';
				break;
			}
		}

		foreach ($this->findAll($UID) as $other)
		{
			if ($other->issuer === $issuer && $other->id !== $provider?->id)
				$errors[] = 'sso_issuer_exists';
		}

		return $errors;
	}

	/**
	 * @throws Exception
	 */
	private function companyOf(int $UID): int
	{
		// @infection-ignore-all every user has a company: 1 is only the default of the installation without companies
		$companyId = $this->usersService->getUserById($UID)->getMain()['company_id'] ?? 1;

		// @infection-ignore-all see above, and the cast is for databases which give text
		return is_numeric($companyId) ? (int) $companyId : 1;
	}
}
