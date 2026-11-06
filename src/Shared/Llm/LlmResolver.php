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

namespace App\Shared\Llm;

use App\Framework\Llm\LlmClient;
use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmException;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;

/**
 * Which LLM a user works with: today his own one. A global LLM of the admin will take its place later,
 * then the own ones of the users are not used.
 */
readonly class LlmResolver
{
	public function __construct(private UserLlmService $userLlmService, private LlmClientFactory $clientFactory)
	{}

	/**
	 * @throws Exception
	 */
	public function isAvailable(int $UID): bool
	{
		return $this->userLlmService->findSettings($UID) !== null;
	}

	/**
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 */
	public function forUser(int $UID): LlmClient
	{
		$connection = $this->userLlmService->findConnection($UID);
		if ($connection === null)
			throw new LlmException('llm_not_configured');

		return $this->clientFactory->create($connection);
	}
}
