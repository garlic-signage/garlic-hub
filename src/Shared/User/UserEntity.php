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

namespace App\Shared\User;

use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * As we use the same entity with OAuth2, we need to implement the UserEntityInterface
 * from PhpLeague-Oauth2-Server
 */
class UserEntity implements UserEntityInterface
{
	/** @var array<string,mixed>  */
	private array $main;
	/** @var array<string,mixed>  */
	private array $contact;
	/** @var array<string,mixed>  */
	private array $stats;
	/** @var array<string,mixed>  */
	private array $security;
	/** @var array<string,mixed>  */
	private array $acl;
	/** @var array<string,mixed>  */
	private array $vip;

	/**
	 * @param array<string,mixed> $main
	 * @param array<string,mixed> $contact
	 * @param array<string,mixed> $stats
	 * @param array<string,mixed> $security
	 * @param array<string,mixed> $acl
	 * @param array<string,mixed> $vip
	 */
	public function __construct(array $main, array $contact, array $stats, array $security, array $acl, array $vip)
	{
		$this->main     = $main;
		$this->contact  = $contact;
		$this->stats    = $stats;
		$this->security = $security;
		$this->acl      = $acl;
		$this->vip      = $vip;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getMain(): array
	{
		return $this->main;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getContact(): array
	{
		return $this->contact;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getStats(): array
	{
		return $this->stats;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSecurity(): array
	{
		return $this->security;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getAcl(): array
	{
		return $this->acl;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getVip(): array
	{
		return $this->vip;
	}

	public function getIdentifier(): string
	{
		return $this->main['id'];
	}
}
