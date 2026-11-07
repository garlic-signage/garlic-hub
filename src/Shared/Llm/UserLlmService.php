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
 * The own AI of a user. It is used for a task only while his company allows it, see LlmResolver.
 */
readonly class UserLlmService extends LlmSettings
{
	public function __construct(
		UserLlmConnectionRepository $connectionRepository,
		UserLlmTaskRepository $taskRepository,
		Crypt $crypt,
		LlmClientFactory $clientFactory
	) {
		parent::__construct($connectionRepository, $taskRepository, $crypt, $clientFactory);
	}

	/**
	 * Only the tasks of the form: the ones the company does not allow are not in it and stay as they are.
	 *
	 * @param array<string,array{connection_id:int, model:string}> $tasks by task
	 * @return list<string> the translation keys of the errors
	 * @throws Exception
	 */
	public function saveTasks(int $UID, array $tasks): array
	{
		$errors = [];
		foreach ($tasks as $task => $input)
		{
			$model      = trim($input['model']);
			$taskErrors  = in_array($task, LlmTask::TASKS, true) ? $this->validateModel($UID, $input['connection_id'], $model) : ['llm_unknown_task'];
			if ($taskErrors !== [])
			{
				$errors = array_merge($errors, $taskErrors);
				continue;
			}

			if ($input['connection_id'] === 0)
				$this->taskRepository->deleteTask($UID, $task);
			else
				$this->taskRepository->storeTask($UID, $task, ['connection_id' => $input['connection_id'], 'model' => $model]);
		}

		return array_values(array_unique($errors));
	}

	/**
	 * @throws Exception
	 */
	protected function releaseTasks(int $ownerId, int $connectionId): void
	{
		$this->taskRepository->deleteWithConnection($ownerId, $connectionId);
	}
}
