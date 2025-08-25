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

use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Represents an entity that defines a scope and provides methods
 * for retrieving its identifier and serializing its data to JSON.
 */
class ScopeEntity implements ScopeEntityInterface
{
	/**
	 * @inheritDoc
	 */
	public function getIdentifier(): string
	{
		return 'dummy';
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize(): string|false
	{
		return json_encode([]);
	}
}