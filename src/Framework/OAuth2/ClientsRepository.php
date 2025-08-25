<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\OAuth2;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use App\Framework\Exceptions\FrameworkException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/**
 * Repository class for managing OAuth2 clients.
 *
 * This class provides functionality to retrieve and validate client entities
 * for OAuth2 authentication and authorization purposes. It extends the SqlBase
 * class and utilizes predefined traits to simplify CRUD operations
 * and query execution.
 */
class ClientsRepository extends SqlBase implements ClientRepositoryInterface
{
	use CrudTraits, FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'oauth2_clients', 'id');
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
	{
		$conditions = ['client_id' => $clientIdentifier];
		$client     = $this->getFirstDataSet($this->findAllBy($conditions));
		if ($client === [])
			throw new FrameworkException('Client not found');

		/** @var array<string,string> $client */
		return new ClientEntity($client);
	}

	/**
	 * @throws Exception
	 */
	public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType = 'authorization_code'): bool
	{
		if ($clientSecret === null || $grantType === null)
			return false;

		/** @var array<string,mixed>|array<empty,empty> $client */
		$client = $this->getFirstDataSet($this->findAllBy(['client_id' => $clientIdentifier]));

		if (empty($client))
			return false;

		if (!password_verify($clientSecret, $client['client_secret']))
			return false;

		if (!str_contains($client['grant_type'], $grantType))
			return false;


		return true;
	}

}