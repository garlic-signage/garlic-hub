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

use InvalidArgumentException;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * Represents a client entity that implements the ClientEntityInterface.
 * Handles initialization and validation of client-related data.
 */
class ClientEntity implements ClientEntityInterface
{
	use ClientTrait;
	use EntityTrait;

	/**
	 * @param array<string,string> $client
	 */
	public function __construct(array $client)
	{
		$clientId = $client['client_id'] ?? '';

		if ($clientId === '')
			throw new InvalidArgumentException('Client ID cannot be an empty.');

		$this->setIdentifier($clientId);
		$this->redirectUri    = $client['redirect_uri'];
		$this->name           = $client['client_name'];
		$this->isConfidential = true;
	}
}