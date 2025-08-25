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
use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

/**
 * Repository class for handling OAuth2 scope-related operations.
 * Extends the SqlBase class to interact with the database for storing and retrieving scope data.
 */
class ScopeRepository extends SqlBase implements ScopeRepositoryInterface
{
	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'oauth2_scopes', 'id');
	}
	public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
	{
		return null; // currently we do not use scopes
	}

	public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, ?string $userIdentifier = null, ?string $authCodeId = null): array
	{
		return []; // currently we do not use scopes
	}
}