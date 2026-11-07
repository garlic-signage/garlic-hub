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
 * The AI of a company, which its admin sets up. For each task he decides who works with which AI: the company with
 * its model for all users or only for the admins, every user with his own one, or nobody. A task without a decision
 * is off. In Core it is the AI of the installation (company 1).
 */
readonly class CompanyLlmService extends LlmSettings
{
	public const string MODE_COMPANY_ALL    = 'company_all';
	public const string MODE_COMPANY_ADMINS = 'company_admins';
	public const string MODE_USER_OWN       = 'user_own';
	public const string MODE_DISABLED       = 'disabled';
	public const array MODES                = [self::MODE_COMPANY_ALL, self::MODE_COMPANY_ADMINS, self::MODE_USER_OWN, self::MODE_DISABLED];

	public function __construct(
		CompanyLlmConnectionRepository $connectionRepository,
		CompanyLlmTaskRepository $taskRepository,
		Crypt $crypt,
		LlmClientFactory $clientFactory
	) {
		parent::__construct($connectionRepository, $taskRepository, $crypt, $clientFactory);
	}

	public static function isCompanyMode(string $mode): bool
	{
		return $mode === self::MODE_COMPANY_ALL || $mode === self::MODE_COMPANY_ADMINS;
	}

	/**
	 * @return array<string,string> the mode of every task
	 * @throws Exception
	 */
	public function findModes(int $companyId): array
	{
		$rows  = $this->taskRepository->findAllOfOwner($companyId);
		$modes = [];
		foreach (LlmTask::TASKS as $task)
			$modes[$task] = (string) ($rows[$task]['mode'] ?? self::MODE_DISABLED);

		return $modes;
	}

	/**
	 * Only the AI of the company needs a provider and a model, the other modes forget them.
	 *
	 * @param array<string,array{mode:string, connection_id:int, model:string}> $tasks by task
	 * @return list<string> the translation keys of the errors
	 * @throws Exception
	 */
	public function saveTasks(int $companyId, array $tasks): array
	{
		$errors = [];
		foreach ($tasks as $task => $input)
		{
			$mode  = $input['mode'];
			$model = trim($input['model']);
			if (!in_array($task, LlmTask::TASKS, true) || !in_array($mode, self::MODES, true))
			{
				$errors[] = 'llm_invalid_mode';
				continue;
			}

			$fields = ['mode' => $mode, 'connection_id' => null, 'model' => ''];
			if (self::isCompanyMode($mode))
			{
				$taskErrors = $input['connection_id'] === 0 ? ['llm_missing_connection'] : $this->validateModel($companyId, $input['connection_id'], $model);
				if ($taskErrors !== [])
				{
					$errors = array_merge($errors, $taskErrors);
					continue;
				}
				$fields = ['mode' => $mode, 'connection_id' => $input['connection_id'], 'model' => $model];
			}

			$this->taskRepository->storeTask($companyId, $task, $fields);
		}

		return array_values(array_unique($errors));
	}

	/**
	 * The task keeps its mode: the AI of the company has no model then until the admin chooses another one.
	 *
	 * @throws Exception
	 */
	protected function releaseTasks(int $ownerId, int $connectionId): void
	{
		$this->taskRepository->updateWithConnection($ownerId, $connectionId, ['connection_id' => null, 'model' => '']);
	}
}
