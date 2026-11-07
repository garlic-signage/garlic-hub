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

use App\Framework\Llm\LlmClientFactory;
use App\Framework\Security\Crypt;
use Doctrine\DBAL\Exception;

/**
 * The LLM of a company, which its admin sets up for the admins or for all users. In Core it is the one of the
 * installation (company 1).
 */
readonly class CompanyLlmService extends LlmSettings
{
	public const string RELEASED_FOR_ADMINS = 'admins';
	public const string RELEASED_FOR_ALL    = 'all';
	public const array RELEASES             = [self::RELEASED_FOR_ADMINS, self::RELEASED_FOR_ALL];

	public function __construct(CompanyLlmRepository $repository, Crypt $crypt, LlmClientFactory $clientFactory)
	{
		parent::__construct($repository, $crypt, $clientFactory);
	}

	/**
	 * @param array{api_type:string, base_url:string, model:string, api_key:string} $input
	 * @return list<string> the translation keys of the errors
	 * @throws Exception
	 */
	public function saveWithRelease(int $companyId, array $input, string $releasedFor): array
	{
		if (!in_array($releasedFor, self::RELEASES, true))
			return ['llm_invalid_release'];

		return $this->save($companyId, $input, ['released_for' => $releasedFor]);
	}

	/**
	 * @return string|null null when the company has no LLM
	 * @throws Exception
	 */
	public function findReleasedFor(int $companyId): ?string
	{
		$row = $this->repository->findFirstById($companyId);

		return $row === [] ? null : (string) $row['released_for'];
	}
}
